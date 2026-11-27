<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Seccion;
use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class AsignacionProfesorConsistenciaTest extends IsolatedDatabaseTestCase
{
    private PeriodoAcademico $periodo;

    private VersionPlanificacion $version;

    private Seccion $seccion;

    private ActividadSeccion $actividad;

    private GrupoSeccion $grupo;

    private Profesor $profesor;

    private Profesor $colaborador;

    private GrupoProfesor $asignacion;

    private int $diaId;

    private int $bloqueId;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = User::create([
            'name' => 'Coordinación de prueba', 'rut' => '11111111',
            'email' => 'coordinacion@example.test', 'password' => 'clave-de-prueba',
        ]);
        foreach (['asignar profesores', 'asignar horarios', 'ver programacion semestral'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($usuario);

        $universidadId = DB::table('universidades')->value('id');
        $this->periodo = PeriodoAcademico::create([
            'universidad_id' => $universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2,
        ]);
        $this->version = VersionPlanificacion::create([
            'periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral actual',
            'tipo' => 'semestral', 'version' => '0.1', 'es_actual' => true,
        ]);
        $asignatura = Asignatura::create([
            'universidad_id' => $universidadId, 'codigo' => 'CONS101', 'nombre' => 'Asignatura de prueba',
        ]);
        $this->seccion = Seccion::create([
            'asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id,
            'codigo' => '1', 'semestre' => 1,
        ]);
        $this->actividad = ActividadSeccion::create([
            'seccion_id' => $this->seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3,
        ]);
        $this->grupo = GrupoSeccion::create([
            'actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:1',
        ]);
        $this->profesor = Profesor::create([
            'universidad_id' => $universidadId, 'nombre' => 'Docente', 'apellido' => 'Responsable',
        ]);
        $this->colaborador = Profesor::create([
            'universidad_id' => $universidadId, 'nombre' => 'Docente', 'apellido' => 'Colaborador',
        ]);
        $this->asignacion = GrupoProfesor::create([
            'grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true,
        ]);
        GrupoProfesor::create([
            'grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->colaborador->id,
            'tipo_participacion' => 'PROFESOR COLABORADOR', 'es_principal' => false,
            'observaciones' => 'Participación del colaborador', 'estado' => true,
        ]);
        $this->diaId = DB::table('dias')->where('numero', 1)->value('id');
        $this->bloqueId = DB::table('bloques_horarios')->where('numero', 1)->value('id');
        $this->url = route('programacion.asignacion.profesores', [
            'periodo_id' => $this->periodo->id, 'seccion_id' => $this->seccion->id,
        ]);
    }

    public function test_no_quita_un_docente_que_imparte_modulos_actuales_y_muestra_el_motivo(): void
    {
        $horario = $this->crearHorario();

        $this->quitarProfesor()
            ->assertRedirect($this->url)
            ->assertSessionHasErrors('asignacion')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('grupos_profesores', ['id' => $this->asignacion->id, 'estado' => true]);
        $this->assertDatabaseHas('horarios', ['id' => $horario->id, 'estado' => true]);
        $this->assertDatabaseHas('horarios_profesores', [
            'horario_id' => $horario->id, 'profesor_id' => $this->profesor->id, 'estado' => true,
        ]);
        $this->get($this->url)->assertOk()->assertSee(
            'No puedes quitar este docente del grupo mientras tenga horarios asignados en la programación semestral actual. Revisa primero sus módulos en Asignación de horarios.'
        );
    }

    public function test_no_quita_un_docente_que_el_horario_hereda_del_grupo(): void
    {
        $horario = $this->crearHorario([], ['estado' => false]);

        $this->quitarProfesor()->assertRedirect($this->url)->assertSessionHasErrors('asignacion');

        $this->assertDatabaseHas('grupos_profesores', ['id' => $this->asignacion->id, 'estado' => true]);
        $this->assertDatabaseHas('horarios', ['id' => $horario->id, 'estado' => true]);
    }

    #[DataProvider('contextosInactivos')]
    public function test_revalida_el_contexto_al_guardar_el_docente(string $relacion, string $atributo, string $error): void
    {
        $cambio = false;
        DB::connection()->beforeStartingTransaction(function () use ($relacion, $atributo, &$cambio) {
            if (! $cambio) {
                $cambio = true;
                $this->$relacion->update([$atributo => false]);
            }
        });

        $this->from($this->url)->post(route('programacion.asignacion.profesores.guardar'), [
            'grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'AYUDANTE', 'es_principal' => '0',
        ])->assertRedirect($this->url)->assertSessionHasErrors($error);

        $this->assertTrue($cambio);
        $this->assertDatabaseHas('grupos_profesores', ['id' => $this->asignacion->id, 'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true]);
    }

    public static function contextosInactivos(): array
    {
        return [
            'grupo' => ['grupo', 'estado_activo', 'grupo_seccion_id'],
            'actividad' => ['actividad', 'estado', 'grupo_seccion_id'],
            'sección' => ['seccion', 'estado_activo', 'grupo_seccion_id'],
            'período' => ['periodo', 'estado', 'grupo_seccion_id'],
            'docente' => ['profesor', 'estado', 'profesor_id'],
        ];
    }

    public function test_permite_quitar_un_docente_sin_modulos(): void
    {
        $this->quitarProfesor()->assertRedirect($this->url)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('grupos_profesores', ['id' => $this->asignacion->id]);
        $this->assertDatabaseHas('profesores', ['id' => $this->profesor->id]);
        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_los_modulos_de_otro_docente_del_grupo_no_impiden_quitar_al_seleccionado(): void
    {
        $horario = $this->crearHorario([], ['profesor_id' => $this->colaborador->id]);

        $this->quitarProfesor()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('grupos_profesores', ['id' => $this->asignacion->id]);
        $this->assertDatabaseHas('horarios_profesores', [
            'horario_id' => $horario->id, 'profesor_id' => $this->colaborador->id,
        ]);
    }

    public function test_los_modulos_del_mismo_docente_en_otro_grupo_no_bloquean(): void
    {
        $otroGrupo = GrupoSeccion::create([
            'actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:2',
        ]);
        $otraAsignacion = GrupoProfesor::create([
            'grupo_seccion_id' => $otroGrupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true,
        ]);
        $horario = $this->crearHorario(['grupo_seccion_id' => $otroGrupo->id]);

        $this->quitarProfesor()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('grupos_profesores', ['id' => $this->asignacion->id]);
        $this->assertDatabaseHas('grupos_profesores', ['id' => $otraAsignacion->id]);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $horario->id, 'profesor_id' => $this->profesor->id]);
    }

    #[DataProvider('horariosQueNoBloquean')]
    public function test_las_relaciones_inactivas_y_otras_versiones_no_bloquean_ni_se_borran(string $caso): void
    {
        $horario = $this->crearHorario();

        switch ($caso) {
            case 'horario_inactivo':
                $horario->update(['estado' => false]);
                break;
            case 'participacion_inactiva':
                $horario->profesores()->update(['estado' => false]);
                HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $this->colaborador->id, 'estado' => true]);
                break;
            case 'semestral_historica':
                $this->version->update(['es_actual' => false]);
                break;
            case 'anterior_actual':
                $this->version->update(['tipo' => 'anterior']);
                break;
            case 'otro_periodo':
                $otroPeriodo = PeriodoAcademico::create([
                    'universidad_id' => $this->periodo->universidad_id,
                    'nombre' => '2027-1', 'anio' => 2027, 'numero_semestre' => 1,
                ]);
                $this->version->update(['periodo_academico_id' => $otroPeriodo->id]);
                break;
        }

        $datosHorario = $horario->fresh()->getAttributes();
        $datosDocente = $horario->profesores()->firstOrFail()->getAttributes();

        $this->quitarProfesor()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('grupos_profesores', ['id' => $this->asignacion->id]);
        $this->assertSame($datosHorario, $horario->fresh()->getAttributes());
        $this->assertSame($datosDocente, $horario->profesores()->firstOrFail()->getAttributes());
    }

    public static function horariosQueNoBloquean(): array
    {
        return [
            'horario inactivo' => ['horario_inactivo'],
            'participación inactiva' => ['participacion_inactiva'],
            'semestral histórica' => ['semestral_historica'],
            'programación anterior actual' => ['anterior_actual'],
            'versión de otro período' => ['otro_periodo'],
        ];
    }

    public function test_tras_retirar_sus_modulos_actuales_permite_quitar_al_docente_y_conserva_el_historico(): void
    {
        $historica = $this->version->replicate();
        $historica->fill(['nombre' => 'Histórica', 'version' => '0.2', 'es_actual' => false])->save();
        $horarioHistorico = $this->crearHorario(['version_planificacion_id' => $historica->id]);
        $horarioActual = $this->crearHorario();

        $this->delete(route('programacion.asignacion.horarios.eliminar', $horarioActual))
            ->assertSessionHasNoErrors();
        $this->quitarProfesor()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('grupos_profesores', ['id' => $this->asignacion->id]);
        $this->assertDatabaseMissing('horarios', ['id' => $horarioActual->id]);
        $this->assertDatabaseHas('horarios', ['id' => $horarioHistorico->id]);
        $this->assertDatabaseHas('horarios_profesores', [
            'horario_id' => $horarioHistorico->id, 'profesor_id' => $this->profesor->id,
        ]);
    }

    public function test_guardar_un_modulo_conserva_solo_el_subconjunto_de_docentes_elegido(): void
    {
        $this->post(route('programacion.asignacion.horarios.guardar'), $this->datosHorario([$this->colaborador->id]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $horario = Horario::sole();
        $this->assertDatabaseCount('horarios_profesores', 1);
        $this->assertDatabaseHas('horarios_profesores', [
            'horario_id' => $horario->id, 'profesor_id' => $this->colaborador->id,
            'tipo_participacion' => 'PROFESOR COLABORADOR', 'es_principal' => false,
            'observaciones' => 'Participación del colaborador', 'estado' => true,
        ]);
        $this->assertDatabaseMissing('horarios_profesores', ['profesor_id' => $this->profesor->id]);
        $this->assertDatabaseCount('grupos_profesores', 2);
    }

    #[DataProvider('cambiosDeAsignacion')]
    public function test_rechaza_el_guardado_si_la_asignacion_cambia_despues_de_validar(bool $eliminar): void
    {
        $cambioAplicado = false;
        // Simular otra operación completada entre la validación y la transacción de guardado.
        DB::connection()->beforeStartingTransaction(function () use (&$cambioAplicado, $eliminar) {
            if ($cambioAplicado) {
                return;
            }
            $cambioAplicado = true;
            if ($eliminar) {
                $this->asignacion->delete();
            } else {
                $this->asignacion->update(['estado' => false]);
            }
        });

        $this->post(route('programacion.asignacion.horarios.guardar'), $this->datosHorario([$this->profesor->id]))
            ->assertSessionHasErrors('profesores_ids');

        $this->assertTrue($cambioAplicado);
        $this->assertDatabaseCount('horarios', 0);
        $this->assertDatabaseCount('horarios_profesores', 0);
    }

    public static function cambiosDeAsignacion(): array
    {
        return ['eliminada' => [true], 'desactivada' => [false]];
    }

    private function crearHorario(array $cambiosHorario = [], array $cambiosDocente = []): Horario
    {
        $horario = Horario::create(array_replace([
            'actividad_seccion_id' => $this->actividad->id, 'grupo_seccion_id' => $this->grupo->id,
            'version_planificacion_id' => $this->version->id, 'dia_id' => $this->diaId,
            'bloque_horario_id' => $this->bloqueId, 'estado' => true,
        ], $cambiosHorario));
        HorarioProfesor::create(array_replace([
            'horario_id' => $horario->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true,
        ], $cambiosDocente));

        return $horario;
    }

    private function quitarProfesor(): TestResponse
    {
        return $this->from($this->url)->delete(
            route('programacion.asignacion.profesores.eliminar', $this->asignacion),
            ['periodo_id' => $this->periodo->id]
        );
    }

    private function datosHorario(array $profesores): array
    {
        return [
            'grupo_seccion_id' => $this->grupo->id, 'dia_id' => $this->diaId,
            'version_id' => $this->version->id,
            'profesores_ids' => $profesores, 'bloques_horarios' => [$this->bloqueId],
        ];
    }
}
