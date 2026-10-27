<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\DisponibilidadProfesor;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Universidad;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\EvaluacionReemplazoDocente;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class IntercambioReemplazoTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private Profesor $origen;

    private Profesor $candidato;

    private Profesor $colaborador;

    private PeriodoAcademico $periodo;

    private VersionPlanificacion $version;

    private GrupoSeccion $grupo;

    private Horario $primero;

    private Horario $segundo;

    private Horario $historico;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->usuario = User::create(['name' => 'Gestión docente', 'rut' => '99999999', 'email' => 'intercambio@example.test', 'password' => 'prueba']);
        foreach (['ver intercambio docente', 'asignar profesores'] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($this->usuario);
        $this->periodo = PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true]);
        $anterior = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Histórica', 'tipo' => 'semestral', 'version' => '0.0', 'estado' => 'Borrador', 'es_actual' => false]);
        $this->origen = Profesor::create(['universidad_id' => $universidad, 'rut' => '11111111-1', 'nombre' => 'Docente', 'apellido' => 'Origen', 'estado' => true]);
        $this->candidato = Profesor::create(['universidad_id' => $universidad, 'rut' => '22222222-K', 'nombre' => 'Ángela', 'apellido' => 'LÓPEZ Muñoz', 'estado' => true]);
        $this->colaborador = Profesor::create(['universidad_id' => $universidad, 'rut' => '33333333-3', 'nombre' => 'Docente', 'apellido' => 'Colaborador', 'estado' => true]);
        $asignatura = Asignatura::create(['universidad_id' => $universidad, 'codigo' => 'MAT1', 'nombre' => 'Matemáticas']);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '1', 'estado_activo' => true]);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 2, 'estado' => true]);
        $this->grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'estado_activo' => true]);
        $this->primero = $this->modulo($this->grupo, 1, 1);
        $this->segundo = $this->modulo($this->grupo, 1, 2);
        $this->historico = $this->modulo($this->grupo, 1, 1, ['version_planificacion_id' => $anterior->id]);
        foreach ([$this->primero, $this->segundo, $this->historico] as $h) {
            $this->asignar($h, $this->origen);
        }
        $this->asignar($this->primero, $this->colaborador, false);
        GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->origen->id, 'tipo_participacion' => 'Responsable', 'es_principal' => true, 'estado' => true]);
        SeccionProfesor::create(['seccion_id' => $seccion->id, 'profesor_id' => $this->origen->id, 'tipo_participacion' => 'Responsable', 'es_principal' => true, 'estado' => true]);
        foreach ([1, 2] as $bloque) {
            DisponibilidadProfesor::create(['profesor_id' => $this->candidato->id, 'periodo_academico_id' => $this->periodo->id, 'dia_id' => 1, 'bloque_horario_id' => $bloque, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);
        }
    }

    public function test_busca_por_rut_formateado_o_nombre_sin_tildes_y_no_interpreta_comodines(): void
    {
        foreach (['22.222.222-k', 'angela lopez'] as $texto) {
            $response = $this->get($this->url(['q' => $texto]))->assertOk();
            $this->assertSame([$this->candidato->id], $response->viewData('candidatos')->pluck('id')->all());
        }
        $this->assertCount(0, $this->get($this->url(['q' => '%']))->assertOk()->viewData('candidatos'));
    }

    public function test_compara_la_clase_elegida_y_la_disponibilidad_sin_modificar_la_base(): void
    {
        $antes = DB::table('horarios_profesores')->get()->toJson();
        $response = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk();
        $evaluacion = $response->viewData('comparacion');
        $this->assertTrue($evaluacion['compatible']);
        $this->assertSame(2, $evaluacion['cubiertos']);
        $this->assertSame(0, $evaluacion['topes']);
        $this->assertFalse($evaluacion['sin_disponibilidad']);
        $this->assertSame($antes, DB::table('horarios_profesores')->get()->toJson());
    }

    public function test_detecta_superposiciones_reales_aunque_los_identificadores_de_bloque_sean_distintos(): void
    {
        $inicio = BloqueHorario::findOrFail(1)->hora_inicio;
        $fin = BloqueHorario::findOrFail(1)->hora_fin;
        $bloque = BloqueHorario::create(['numero' => 80, 'hora_inicio' => $inicio, 'hora_fin' => $fin, 'estado' => true]);
        $this->asignar($this->modulo($this->grupo, 1, $bloque->id), $this->candidato);
        $comparacion = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion');
        $this->assertFalse($comparacion['compatible']);
        $this->assertSame(1, $comparacion['topes']);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasErrors('reemplazo');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_los_bloques_adyacentes_no_se_consideran_superpuestos_y_los_historicos_no_bloquean(): void
    {
        $fin = BloqueHorario::findOrFail(2)->hora_fin;
        $bloque = BloqueHorario::create(['numero' => 81, 'hora_inicio' => $fin, 'hora_fin' => '23:00:00', 'estado' => true]);
        $this->asignar($this->modulo($this->grupo, 1, $bloque->id), $this->candidato);
        $this->asignar($this->historico, $this->candidato);
        $this->assertTrue($this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion')['compatible']);
    }

    public function test_revalida_los_topes_que_aparecen_despues_de_abrir_la_comparacion(): void
    {
        $datos = $this->datos();
        $this->assertTrue($this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion')['compatible']);
        $otro = GrupoSeccion::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'codigo' => 'G:2', 'estado_activo' => true]);
        $this->asignar($this->modulo($otro, 1, 1), $this->candidato);
        $this->post(route('programacion.intercambio.aplicar'), $datos)->assertSessionHasErrors('reemplazo');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_disponibilidades_contiguas_pueden_cubrir_un_modulo_completo(): void
    {
        DisponibilidadProfesor::where('profesor_id', $this->candidato->id)->where('bloque_horario_id', 1)->delete();
        $original = BloqueHorario::findOrFail(1);
        foreach ([['hora_inicio' => $original->hora_inicio, 'hora_fin' => '08:35:00'], ['hora_inicio' => '08:35:00', 'hora_fin' => $original->hora_fin]] as $indice => $horas) {
            $bloque = BloqueHorario::create($horas + ['numero' => 85 + $indice, 'estado' => true]);
            DisponibilidadProfesor::create(['profesor_id' => $this->candidato->id, 'periodo_academico_id' => $this->periodo->id, 'dia_id' => 1, 'bloque_horario_id' => $bloque->id, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);
        }
        $comparacion = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion');
        $this->assertTrue($comparacion['compatible']);
        $this->assertSame(2, $comparacion['cubiertos']);
    }

    public function test_rechaza_un_candidato_fuera_de_su_disponibilidad_declarada(): void
    {
        DisponibilidadProfesor::where('profesor_id', $this->candidato->id)->where('bloque_horario_id', 2)->delete();
        $comparacion = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion');
        $this->assertSame(1, $comparacion['fuera']);
        $this->assertFalse($comparacion['compatible']);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasErrors('reemplazo');
    }

    public function test_sin_disponibilidad_informada_exige_confirmacion_explicita(): void
    {
        DisponibilidadProfesor::where('profesor_id', $this->candidato->id)->delete();
        $comparacion = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion');
        $this->assertTrue($comparacion['sin_disponibilidad']);
        $this->assertTrue($comparacion['compatible']);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasErrors('reemplazo');
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos(['confirmar_disponibilidad' => '1']))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_aplica_solo_los_modulos_seleccionados_y_conserva_codocentes_historicos_y_horarios(): void
    {
        $antes = DB::table('horarios')->orderBy('id')->get()->toJson();
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasNoErrors()
            ->assertRedirect(route('programacion.intercambio', ['docente_id' => $this->candidato->id, 'grupo_id' => $this->grupo->id]));
        foreach ([$this->primero, $this->segundo] as $h) {
            $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $h->id, 'profesor_id' => $this->origen->id, 'estado' => false]);
            $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $h->id, 'profesor_id' => $this->candidato->id, 'estado' => true, 'es_principal' => true]);
        }
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->primero->id, 'profesor_id' => $this->colaborador->id, 'estado' => true]);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->historico->id, 'profesor_id' => $this->origen->id, 'estado' => true]);
        $this->assertDatabaseHas('grupos_profesores', ['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->origen->id, 'estado' => false]);
        $this->assertDatabaseHas('grupos_profesores', ['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->candidato->id, 'estado' => true]);
        $this->assertDatabaseHas('profesores_versiones', ['version_planificacion_id' => $this->version->id, 'profesor_id' => $this->candidato->id]);
        $this->assertDatabaseCount('historial_acciones', 1);
        $this->assertSame($antes, DB::table('horarios')->orderBy('id')->get()->toJson());
        $this->get($this->url(['docente_id' => $this->candidato->id]))->assertOk()->assertViewHas('objetivo', fn ($h) => $h->count() === 2);
        $carga = $this->get(route('programacion.intercambio.carga', ['asignatura_id' => $this->grupo->actividadSeccion->seccion->asignatura_id]))->assertOk()->viewData('resultados');
        $this->assertFalse($carga->contains(fn ($fila) => $fila['profesor']->id === $this->origen->id));
        $this->assertSame(2, $carga->firstWhere('profesor.id', $this->candidato->id)['cantidad_horarios']);
    }

    public function test_conserva_el_vinculo_de_seccion_si_el_docente_sigue_impartiendo_otra_clase(): void
    {
        $otro = GrupoSeccion::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'codigo' => 'G:2', 'estado_activo' => true]);
        $modulo = $this->modulo($otro, 3, 3);
        $this->asignar($modulo, $this->origen);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $modulo->id, 'profesor_id' => $this->origen->id, 'estado' => true]);
        $this->assertDatabaseHas('secciones_profesores', ['seccion_id' => $this->grupo->actividadSeccion->seccion_id, 'profesor_id' => $this->origen->id, 'estado' => true]);
        $carga = $this->get(route('programacion.intercambio.carga', ['asignatura_id' => $this->grupo->actividadSeccion->seccion->asignatura_id]))->assertOk()->viewData('resultados');
        $this->assertSame(1, $carga->firstWhere('profesor.id', $this->origen->id)['cantidad_horarios']);
    }

    public function test_una_indisponibilidad_explicita_prevalece_sobre_la_cobertura_positiva(): void
    {
        $bloque = BloqueHorario::create(['numero' => 82, 'hora_inicio' => BloqueHorario::find(1)->hora_inicio, 'hora_fin' => BloqueHorario::find(1)->hora_fin, 'estado' => true]);
        DisponibilidadProfesor::create(['profesor_id' => $this->candidato->id, 'periodo_academico_id' => $this->periodo->id, 'dia_id' => 1, 'bloque_horario_id' => $bloque->id, 'tipo_disponibilidad' => 'No disponible', 'estado' => true]);
        $comparacion = $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion');
        $this->assertFalse($comparacion['compatible']);
        $this->assertSame(1, $comparacion['fuera']);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos(['confirmar_disponibilidad' => '1']))->assertSessionHasErrors('reemplazo');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('cambiosInvalidos')]
    public function test_revalida_el_contexto_antes_de_aplicar(string $cambio): void
    {
        $datos = $this->datos();
        match ($cambio) {
            'huella' => $datos['huella'] = str_repeat('0', 64),
            'hora' => $this->primero->update(['dia_id' => 3]),
            'publicado' => $this->version->update(['estado' => 'Publicado']),
            'version' => $this->version->update(['es_actual' => false]),
            'grupo' => $this->grupo->update(['estado_activo' => false]),
            'candidato' => $this->candidato->update(['estado' => false]),
        };
        $this->post(route('programacion.intercambio.aplicar'), $datos)->assertSessionHasErrors('reemplazo');
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->segundo->id, 'profesor_id' => $this->origen->id, 'estado' => true]);
    }

    public static function cambiosInvalidos(): array
    {
        return array_map(fn ($cambio) => [$cambio], ['huella', 'hora', 'publicado', 'version', 'grupo', 'candidato']);
    }

    public function test_el_permiso_de_consulta_no_autoriza_aplicar_reemplazos(): void
    {
        $this->usuario->revokePermissionTo('asignar profesores');
        $this->actingAs($this->usuario->fresh());
        $this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->assertDontSee('Confirmar reemplazo');
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_admite_candidatos_de_otra_institucion_ni_al_mismo_docente(): void
    {
        $otra = Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA']);
        $ajeno = Profesor::create(['universidad_id' => $otra->id, 'nombre' => 'Docente', 'apellido' => 'Ajeno', 'estado' => true]);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos(['candidato_id' => $ajeno->id]))->assertSessionHasErrors('reemplazo');
        $this->post(route('programacion.intercambio.aplicar'), $this->datos(['candidato_id' => $this->origen->id]))->assertSessionHasErrors('candidato_id');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_el_permiso_de_asignacion_no_sustituye_el_acceso_al_modulo_de_intercambio(): void
    {
        $this->usuario->revokePermissionTo('ver intercambio docente');
        $this->actingAs($this->usuario->fresh());
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_un_codocente_puede_asumir_la_clase_sin_considerar_su_propio_modulo_un_tope(): void
    {
        $this->asignar($this->primero, $this->candidato, false);
        GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->candidato->id, 'tipo_participacion' => 'Colaborador', 'es_principal' => false, 'estado' => true]);
        $this->assertTrue($this->get($this->url(['candidato_id' => $this->candidato->id]))->assertOk()->viewData('comparacion')['compatible']);
        $this->post(route('programacion.intercambio.aplicar'), $this->datos())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, HorarioProfesor::where('horario_id', $this->primero->id)->where('profesor_id', $this->candidato->id)->count());
        $this->assertDatabaseHas('grupos_profesores', ['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->candidato->id, 'estado' => true, 'es_principal' => true]);
    }

    private function url(array $cambios = []): string
    {
        return route('programacion.intercambio', array_replace(['docente_id' => $this->origen->id, 'grupo_id' => $this->grupo->id], $cambios));
    }

    private function datos(array $cambios = []): array
    {
        $objetivo = app(EvaluacionReemplazoDocente::class)->carga($this->origen, $this->periodo, $this->version)->where('grupo_seccion_id', $this->grupo->id)->values();

        return array_replace(['docente_id' => $this->origen->id, 'candidato_id' => $this->candidato->id, 'grupo_id' => $this->grupo->id,
            'periodo_id' => $this->periodo->id, 'version_id' => $this->version->id, 'huella' => app(EvaluacionReemplazoDocente::class)->huella($objetivo)], $cambios);
    }

    private function modulo(GrupoSeccion $grupo, int $dia, int $bloque, array $cambios = []): Horario
    {
        return Horario::create(array_replace(['actividad_seccion_id' => $grupo->actividad_seccion_id, 'grupo_seccion_id' => $grupo->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => $dia, 'bloque_horario_id' => $bloque, 'estado' => true], $cambios));
    }

    private function asignar(Horario $horario, Profesor $profesor, bool $principal = true): void
    {
        HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $profesor->id, 'tipo_participacion' => $principal ? 'Responsable' : 'Colaborador', 'es_principal' => $principal, 'estado' => true]);
    }
}
