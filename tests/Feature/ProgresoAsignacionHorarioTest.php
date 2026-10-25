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
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ProgresoAsignacionHorarioTest extends IsolatedDatabaseTestCase
{
    private PeriodoAcademico $periodo;

    private VersionPlanificacion $version;

    private Seccion $seccion;

    private ActividadSeccion $actividad;

    private GrupoSeccion $grupo;

    private Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = User::create([
            'name' => 'Coordinación de prueba', 'rut' => '11111111',
            'email' => 'progreso@example.test', 'password' => 'clave-de-prueba',
        ]);
        foreach (['asignar horarios', 'ver programacion semestral'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($usuario);

        $universidadId = DB::table('universidades')->value('id');
        $this->periodo = PeriodoAcademico::create([
            'universidad_id' => $universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);
        $this->version = VersionPlanificacion::create([
            'periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral actual',
            'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true,
        ]);
        $asignatura = Asignatura::create([
            'universidad_id' => $universidadId, 'codigo' => 'PROG101', 'nombre' => 'Asignatura de prueba',
        ]);
        $this->seccion = Seccion::create([
            'asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id,
            'codigo' => '1', 'semestre' => 1, 'estado_activo' => true,
        ]);
        $this->actividad = ActividadSeccion::create([
            'seccion_id' => $this->seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3, 'estado' => true,
        ]);
        $this->grupo = GrupoSeccion::create([
            'actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:1', 'estado_activo' => true,
        ]);
        $this->profesor = Profesor::create([
            'universidad_id' => $universidadId, 'nombre' => 'Profesor', 'apellido' => 'De prueba', 'estado' => true,
        ]);
        GrupoProfesor::create([
            'grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true,
        ]);
    }

    public function test_el_exceso_no_aparece_completo_ni_permite_agregar_y_conserva_la_eliminacion_de_modulos(): void
    {
        $this->actividad->update(['cantidad_horas' => 2]);
        $horarios = [$this->crearHorario(1), $this->crearHorario(2), $this->crearHorario(3)];
        $antes = $this->snapshot();

        $response = $this->pantalla()
            ->assertOk()
            ->assertSee('Exceso de módulos')
            ->assertSee('150%')
            ->assertSee('Sobra 1 módulo.')
            ->assertDontSee('Módulos completos')
            ->assertDontSee('class="js-form-horario ', false);

        foreach ($horarios as $horario) {
            $this->assertHorarioEliminable($response, $horario);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_el_estado_completo_exige_igualdad_y_no_muestra_el_formulario_para_agregar(): void
    {
        $this->actividad->update(['cantidad_horas' => 2]);
        $horarios = [$this->crearHorario(1), $this->crearHorario(2)];
        $antes = $this->snapshot();

        $response = $this->pantalla()
            ->assertOk()
            ->assertSee('Módulos completos')
            ->assertDontSee('Exceso de módulos')
            ->assertDontSee('class="js-form-horario ', false);

        foreach ($horarios as $horario) {
            $this->assertHorarioEliminable($response, $horario);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_un_grupo_pendiente_muestra_el_formulario_y_mantiene_sus_modulos_actuales(): void
    {
        $horario = $this->crearHorario(1);
        $antes = $this->snapshot();

        $response = $this->pantalla()
            ->assertOk()
            ->assertSee('Asignación pendiente')
            ->assertSee('class="js-form-horario ', false)
            ->assertDontSee('Módulos completos')
            ->assertDontSee('Exceso de módulos');

        $this->assertHorarioEliminable($response, $horario);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_una_carga_sin_definir_no_permite_agregar_pero_permite_revisar_y_retirar_lo_existente(): void
    {
        $this->actividad->update(['cantidad_horas' => 0]);
        $horario = $this->crearHorario(1);
        $antes = $this->snapshot();

        $response = $this->pantalla()
            ->assertOk()
            ->assertSee('Carga horaria sin definir')
            ->assertDontSee('Módulos completos')
            ->assertDontSee('class="js-form-horario ', false);

        $this->assertHorarioEliminable($response, $horario);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_los_historicos_y_los_modulos_inactivos_no_inflan_el_progreso_del_grupo(): void
    {
        $horarioActual = $this->crearHorario(1);
        $historica = $this->version->replicate();
        $historica->fill(['nombre' => 'Semestral histórica', 'version' => '0.2', 'es_actual' => false])->save();
        $horarioHistorico = $this->crearHorario(2, [
            'version_planificacion_id' => $historica->id,
            'observaciones' => 'Marca histórica que no se debe listar',
        ]);
        $horarioInactivo = $this->crearHorario(3, [
            'estado' => false, 'observaciones' => 'Marca inactiva que no se debe listar',
        ]);
        $antes = $this->snapshot();

        $response = $this->pantalla()
            ->assertOk()
            ->assertSee('Asignación pendiente')
            ->assertSee('33,3%')
            ->assertSee('class="js-form-horario ', false)
            ->assertDontSee('Módulos completos')
            ->assertDontSee('Marca histórica que no se debe listar')
            ->assertDontSee('Marca inactiva que no se debe listar');

        $this->assertHorarioEliminable($response, $horarioActual);
        foreach ([$horarioHistorico, $horarioInactivo] as $horario) {
            $response->assertDontSee('action="'.e(route('programacion.asignacion.horarios.eliminar', $horario)).'"', false);
        }
        $this->assertSame($antes, $this->snapshot());
    }

    private function pantalla(): TestResponse
    {
        return $this->get(route('programacion.asignacion.horarios', [
            'periodo_id' => $this->periodo->id, 'seccion_id' => $this->seccion->id,
        ]));
    }

    private function crearHorario(int $numeroBloque, array $cambios = []): Horario
    {
        $horario = Horario::create(array_replace([
            'actividad_seccion_id' => $this->actividad->id, 'grupo_seccion_id' => $this->grupo->id,
            'version_planificacion_id' => $this->version->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'),
            'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', $numeroBloque)->value('id'),
            'estado' => true,
        ], $cambios));
        HorarioProfesor::create([
            'horario_id' => $horario->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true,
        ]);

        return $horario;
    }

    private function assertHorarioEliminable(TestResponse $response, Horario $horario): void
    {
        $response->assertSee('action="'.e(route('programacion.asignacion.horarios.eliminar', $horario)).'"', false);
    }

    private function snapshot(): array
    {
        $filas = [];
        foreach (['actividades_seccion', 'grupos_seccion', 'grupos_profesores', 'horarios', 'horarios_profesores'] as $tabla) {
            $filas[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $filas;
    }
}
