<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ProgramacionSemestralTest extends IsolatedDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'rut' => '11111111',
            'email' => 'prueba@example.test',
            'password' => 'clave-de-prueba',
        ]);
        $usuario->givePermissionTo(Permission::findOrCreate('ver programacion semestral', 'web'));
        $this->actingAs($usuario);
    }

    public function test_al_entrar_no_carga_los_horarios_y_pide_un_filtro(): void
    {
        $this->crearProgramacion();
        DB::enableQueryLog();

        $this->get(route('programacion.semestral'))
            ->assertOk()
            ->assertViewHas('hayFiltroAplicado', false)
            ->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty())
            ->assertSee('Selecciona un filtro para consultar los horarios.')
            ->assertDontSee('No hay horarios para los filtros seleccionados.');

        $cargasCompletas = collect(DB::getQueryLog())->filter(
            fn ($query) => preg_match('/^select \* from ["`]horarios["`]/i', $query['query'])
        );
        $this->assertCount(0, $cargasCompletas);
    }

    public function test_cambiar_solo_periodo_y_version_no_carga_toda_la_programacion(): void
    {
        $datos = $this->crearProgramacion();

        $this->get(route('programacion.semestral', [
            'periodo_id' => $datos['periodo_id'],
            'version_id' => $datos['version_id'],
        ]))
            ->assertOk()
            ->assertViewHas('hayFiltroAplicado', false)
            ->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty());
    }

    public function test_los_filtros_vacios_no_disparan_la_carga(): void
    {
        $this->crearProgramacion();

        $this->get(route('programacion.semestral', [
            'sede_id' => '', 'carrera_id' => '', 'semestre' => '',
            'seccion_numero' => '', 'asignatura_id' => '', 'profesor_id' => '',
        ]))
            ->assertOk()
            ->assertViewHas('hayFiltroAplicado', false)
            ->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty());
    }

    #[DataProvider('filtrosAcademicos')]
    public function test_cada_filtro_consulta_solo_los_horarios_activos_de_la_version_seleccionada(string $filtro): void
    {
        $datos = $this->crearProgramacion();

        $this->get(route('programacion.semestral', [$filtro => $datos[$filtro]]))
            ->assertOk()
            ->assertViewHas('hayFiltroAplicado', true)
            ->assertViewHas('horarios', fn ($horarios) => $horarios->pluck('id')->all() === [$datos['horario_id']])
            ->assertDontSee('Selecciona un filtro para consultar los horarios.');
    }

    public static function filtrosAcademicos(): array
    {
        return array_map(fn ($filtro) => [$filtro], [
            'sede_id', 'carrera_id', 'semestre', 'seccion_numero', 'asignatura_id', 'profesor_id',
        ]);
    }

    public function test_distingue_una_busqueda_sin_resultados_de_la_vista_inicial(): void
    {
        $this->crearProgramacion();

        $this->get(route('programacion.semestral', ['asignatura_id' => 999999]))
            ->assertOk()
            ->assertViewHas('hayFiltroAplicado', true)
            ->assertSee('No hay horarios para los filtros seleccionados.')
            ->assertDontSee('Selecciona un filtro para consultar los horarios.');
    }

    public function test_conserva_el_aviso_cuando_no_existen_versiones(): void
    {
        $this->get(route('programacion.semestral'))
            ->assertOk()
            ->assertSee('Todavía no existe una programación semestral.')
            ->assertDontSee('Selecciona un filtro para consultar los horarios.');
    }

    public function test_incluye_el_sabado_cuando_la_consulta_tiene_clases_ese_dia(): void
    {
        $datos = $this->crearProgramacion();
        $horario = Horario::findOrFail($datos['horario_id']);
        $sabadoId = DB::table('dias')->where('numero', 6)->value('id');
        $copia = $horario->replicate();
        $copia->dia_id = $sabadoId;
        $copia->save();

        $this->get(route('programacion.semestral', ['asignatura_id' => $datos['asignatura_id']]))
            ->assertOk()
            ->assertViewHas('diasVisualizacion', fn ($dias) => $dias->contains('id', $sabadoId))
            ->assertViewHas('horarios', fn ($horarios) => $horarios->count() === 2);
    }

    private function crearProgramacion(): array
    {
        $universidadId = DB::table('universidades')->value('id');
        $periodo = PeriodoAcademico::create([
            'universidad_id' => $universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2,
        ]);
        $version = VersionPlanificacion::create([
            'periodo_academico_id' => $periodo->id, 'nombre' => 'Borrador de prueba',
            'tipo' => 'semestral', 'version' => '0.1', 'es_actual' => true,
        ]);
        $otraVersion = VersionPlanificacion::create([
            'periodo_academico_id' => $periodo->id, 'nombre' => 'Otra versión',
            'tipo' => 'semestral', 'version' => '0.2', 'es_actual' => false,
        ]);
        $diaId = DB::table('dias')->where('numero', 1)->value('id');
        $bloques = DB::table('bloques_horarios')->orderBy('numero')->pluck('id');
        $datos = [];

        foreach ([1, 2] as $numero) {
            $sede = Sede::create(['universidad_id' => $universidadId, 'nombre' => "Sede $numero"]);
            $carrera = Carrera::create([
                'universidad_id' => $universidadId, 'sede_id' => $sede->id, 'nombre' => "Carrera $numero",
            ]);
            $carrera->sedes()->attach($sede);
            $asignatura = Asignatura::create([
                'universidad_id' => $universidadId, 'codigo' => "TEST$numero", 'nombre' => "Asignatura $numero",
            ]);
            $seccion = Seccion::create([
                'asignatura_id' => $asignatura->id, 'periodo_academico_id' => $periodo->id,
                'codigo' => (string) $numero, 'semestre' => $numero,
            ]);
            $seccion->carreras()->attach($carrera);
            $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
            $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
            $profesor = Profesor::create([
                'universidad_id' => $universidadId, 'nombre' => "Docente $numero", 'apellido' => 'Prueba',
            ]);
            $horario = Horario::create([
                'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
                'version_planificacion_id' => $version->id, 'dia_id' => $diaId,
                'bloque_horario_id' => $bloques[$numero - 1],
            ]);
            HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $profesor->id]);

            if ($numero === 1) {
                $datos = [
                    'periodo_id' => $periodo->id, 'version_id' => $version->id, 'sede_id' => $sede->id,
                    'carrera_id' => $carrera->id, 'semestre' => $numero, 'seccion_numero' => (string) $numero,
                    'asignatura_id' => $asignatura->id, 'profesor_id' => $profesor->id, 'horario_id' => $horario->id,
                ];
                foreach ([['estado' => false], ['version_planificacion_id' => $otraVersion->id]] as $index => $cambios) {
                    $copia = $horario->replicate();
                    $copia->bloque_horario_id = $bloques[$index + 3];
                    $copia->fill($cambios)->save();
                }
            }
        }

        return $datos;
    }
}
