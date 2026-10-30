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
use App\Models\ProfesorVersion;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Sede;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\FiltrosProgramacionAnterior;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class FiltrosProgramacionAnteriorTest extends IsolatedDatabaseTestCase
{
    public function test_sabado_depende_de_los_horarios_activos_de_la_consulta_actual(): void
    {
        $parametros = $this->contexto + ['asignatura_id' => $this->filas[1]['asignatura_id']];
        $this->get(route('programacion.index', $parametros))->assertOk()
            ->assertViewHas('diasVisualizacion', fn ($dias) => ! $dias->contains('numero', 6));
        $sabado = DB::table('dias')->where('numero', 6)->value('id');
        $horario = Horario::findOrFail($this->filas[1]['horario_id'])->replicate();
        $horario->dia_id = $sabado;
        $horario->estado = false;
        $horario->save();
        $this->get(route('programacion.index', $parametros))->assertOk()
            ->assertViewHas('diasVisualizacion', fn ($dias) => ! $dias->contains('numero', 6));
        $horario->update(['estado' => true]);
        $this->get(route('programacion.index', $parametros))->assertOk()
            ->assertViewHas('diasVisualizacion', fn ($dias) => $dias->contains('numero', 6));
        $this->get(route('programacion.index', $this->contexto + ['asignatura_id' => $this->filas[0]['asignatura_id']]))->assertOk()
            ->assertViewHas('diasVisualizacion', fn ($dias) => ! $dias->contains('numero', 6));
    }

    private PeriodoAcademico $periodo;
    private VersionPlanificacion $version;
    private VersionPlanificacion $otraVersion;
    private array $filas;
    private array $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Consulta anterior', 'rut' => '71111111', 'email' => 'filtros@example.test', 'password' => 'prueba']);
        $user->givePermissionTo(Permission::findOrCreate('ver programacion anterior', 'web'));
        $this->actingAs($user);
        $universidad = DB::table('universidades')->value('id');
        $this->periodo = PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Importación', 'tipo' => 'anterior', 'version' => '1.0', 'es_actual' => true]);
        $this->otraVersion = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Otra importación', 'tipo' => 'anterior', 'version' => '2.0']);
        $this->contexto = ['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id];
        $sedes = $carreras = $profesores = $asignaturas = [];
        foreach (['Norte', 'Sur'] as $nombre) {
            $sedes[] = Sede::create(['universidad_id' => $universidad, 'nombre' => $nombre]);
            $carrera = Carrera::create(['universidad_id' => $universidad, 'sede_id' => end($sedes)->id, 'nombre' => 'Carrera '.$nombre]);
            $carrera->sedes()->attach(end($sedes));
            $carreras[] = $carrera;
        }
        foreach (['Ana', 'Beto', 'Caro'] as $nombre) {
            $profesores[] = Profesor::create(['universidad_id' => $universidad, 'nombre' => $nombre, 'apellido' => 'Docente']);
        }
        foreach (['MAT', 'FIS', 'QUI'] as $codigo) {
            $asignaturas[] = Asignatura::create(['universidad_id' => $universidad, 'codigo' => $codigo, 'nombre' => 'Asignatura '.$codigo]);
        }
        $this->filas = [];
        foreach ([[0, 0, 0, 1, '01'], [0, 1, 1, 2, '02'], [1, 0, 2, 3, '03'], [1, 2, 0, 1, '04']] as [$sede, $asignatura, $profesor, $semestre, $codigo]) {
            $seccion = Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $asignaturas[$asignatura]->id, 'semestre' => $semestre, 'codigo' => $codigo]);
            $seccion->carreras()->attach($carreras[$sede]);
            $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
            $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
            $horario = Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id'), 'estado' => true]);
            HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $profesores[$profesor]->id, 'estado' => true]);
            $this->filas[] = ['sede_id' => $sedes[$sede]->id, 'carrera_id' => $carreras[$sede]->id, 'semestre' => $semestre, 'seccion_numero' => $codigo, 'asignatura_id' => $asignaturas[$asignatura]->id, 'profesor_id' => $profesores[$profesor]->id, 'horario_id' => $horario->id, 'seccion_id' => $seccion->id];
        }
        $copia = Horario::findOrFail($this->filas[2]['horario_id'])->replicate();
        $copia->version_planificacion_id = $this->otraVersion->id;
        $copia->save();
        HorarioProfesor::create(['horario_id' => $copia->id, 'profesor_id' => $this->filas[2]['profesor_id']]);
    }

    #[DataProvider('campos')]
    public function test_cualquier_filtro_limita_las_opciones_de_todos_los_demas(string $campo): void
    {
        $seleccion = [$campo => $this->filas[1][$campo]];
        $this->comprobarOpciones($seleccion, $this->consultar($seleccion)->assertOk()->json());
    }

    public static function campos(): array
    {
        return array_map(fn ($campo) => [$campo], FiltrosProgramacionAnterior::ACADEMICOS);
    }

    #[DataProvider('ordenes')]
    public function test_se_puede_empezar_por_cualquier_extremo_sin_perder_selecciones_compatibles(array $orden): void
    {
        $seleccion = $prioridad = [];
        foreach ($orden as $campo) {
            $seleccion[$campo] = $this->filas[1][$campo];
            array_unshift($prioridad, $campo);
            $datos = $this->consultar($seleccion + ['cambiado' => $campo, 'prioridad' => $prioridad])->assertOk()->json();
            foreach ($seleccion as $nombre => $valor) {
                $this->assertSame((string) $valor, (string) $datos['seleccionados'][$nombre]);
            }
            $this->comprobarOpciones($seleccion, $datos);
        }
    }

    public static function ordenes(): array
    {
        $campos = FiltrosProgramacionAnterior::ACADEMICOS;
        return [[$campos], [array_reverse($campos)], [['profesor_id', 'sede_id', 'asignatura_id', 'semestre', 'carrera_id', 'seccion_numero']]];
    }

    public function test_combinar_y_quitar_filtros_amplia_solo_las_opciones_compatibles(): void
    {
        foreach ([['profesor_id' => $this->filas[0]['profesor_id']], ['profesor_id' => $this->filas[0]['profesor_id'], 'sede_id' => $this->filas[0]['sede_id']], ['sede_id' => $this->filas[0]['sede_id']], []] as $seleccion) {
            $this->comprobarOpciones($seleccion, $this->consultar($seleccion)->assertOk()->json());
        }
    }

    public function test_un_cambio_incompatible_conserva_la_ultima_eleccion_y_limpia_las_anteriores_incompatibles(): void
    {
        $seleccion = Arr::only($this->filas[1], FiltrosProgramacionAnterior::ACADEMICOS);
        $seleccion['profesor_id'] = $this->filas[2]['profesor_id'];
        $datos = $this->consultar($seleccion + ['cambiado' => 'profesor_id', 'prioridad' => array_reverse(FiltrosProgramacionAnterior::ACADEMICOS)])->assertOk()->json();
        $this->assertSame($this->filas[2]['profesor_id'], $datos['seleccionados']['profesor_id']);
        foreach (array_diff(FiltrosProgramacionAnterior::ACADEMICOS, ['profesor_id']) as $campo) $this->assertNull($datos['seleccionados'][$campo]);
        $this->comprobarOpciones(['profesor_id' => $this->filas[2]['profesor_id']], $datos);
    }

    public function test_al_entrar_no_carga_todos_los_horarios_y_al_aplicar_usa_los_mismos_filtros(): void
    {
        DB::enableQueryLog();
        $this->get(route('programacion.index', $this->contexto))->assertOk()->assertViewHas('hayFiltroAplicado', false)
            ->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty());
        $this->assertCount(0, collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^select \* from ["`]horarios["`]/i', $query['query'])));
        $this->get(route('programacion.index', $this->contexto + ['profesor_id' => $this->filas[1]['profesor_id']]))
            ->assertOk()->assertViewHas('horarios', fn ($horarios) => $horarios->pluck('id')->all() === [$this->filas[1]['horario_id']]);
        $this->get(route('programacion.index', $this->contexto + ['sede_id' => $this->filas[0]['sede_id'], 'asignatura_id' => $this->filas[3]['asignatura_id']]))
            ->assertOk()->assertViewHas('hayFiltroAplicado', true)->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty());
    }

    public function test_los_docentes_respetan_la_asignacion_del_horario_y_el_respaldo_de_la_seccion(): void
    {
        $fila = $this->filas[0];
        SeccionProfesor::create(['seccion_id' => $fila['seccion_id'], 'profesor_id' => $this->filas[1]['profesor_id']]);
        $this->consultar(['seccion_numero' => '01'])->assertJsonPath('profesores.0.id', $fila['profesor_id'])->assertJsonCount(1, 'profesores');
        HorarioProfesor::where('horario_id', $fila['horario_id'])->update(['estado' => false]);
        $this->consultar(['seccion_numero' => '01'])->assertJsonPath('profesores.0.id', $this->filas[1]['profesor_id'])->assertJsonCount(1, 'profesores');
        $this->get(route('programacion.index', $this->contexto + ['profesor_id' => $this->filas[1]['profesor_id']]))
            ->assertViewHas('horarios', fn ($horarios) => $horarios->pluck('id')->sort()->values()->all() === [$fila['horario_id'], $this->filas[1]['horario_id']]);
    }

    public function test_el_nombre_historico_del_docente_se_conserva_en_opciones_y_resultados_sin_modificar_su_ficha(): void
    {
        $id = $this->filas[1]['profesor_id'];
        ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $id, 'nombre' => 'Nombre histórico', 'apellido' => 'Conservado']);
        $this->consultar(['profesor_id' => $id])->assertOk()->assertJsonFragment(['id' => $id, 'nombre' => 'Nombre histórico Conservado']);
        $this->get(route('programacion.index', $this->contexto + ['profesor_id' => $id]))->assertOk()->assertSee('Nombre histórico Conservado');
        $this->assertSame('Beto', Profesor::findOrFail($id)->nombre);
    }

    public function test_sede_y_carrera_deben_corresponder_a_la_misma_relacion(): void
    {
        Seccion::findOrFail($this->filas[0]['seccion_id'])->carreras()->attach($this->filas[2]['carrera_id']);
        $this->get(route('programacion.index', $this->contexto + ['sede_id' => $this->filas[0]['sede_id'], 'carrera_id' => $this->filas[2]['carrera_id']]))
            ->assertOk()->assertViewHas('horarios', fn ($horarios) => $horarios->isEmpty());
    }

    public function test_periodos_y_versiones_solo_ofrecen_combinaciones_existentes_y_el_cambio_de_contexto_recalcula_todo(): void
    {
        $datos = $this->consultar(['profesor_id' => $this->filas[1]['profesor_id']])->assertOk()->json();
        $this->assertSame([$this->periodo->id], array_column($datos['periodos'], 'id'));
        $this->assertSame([$this->version->id], array_column($datos['versiones'], 'id'));
        $datos = $this->consultar(['version_id' => $this->otraVersion->id, 'cambiado' => 'version_id'])->assertOk()->json();
        $this->assertSame([$this->filas[2]['asignatura_id']], array_column($datos['asignaturas'], 'id'));
        $this->assertSame([$this->filas[2]['profesor_id']], array_column($datos['profesores'], 'id'));
    }

    public function test_no_incluye_horarios_inactivos_ni_versiones_semestrales(): void
    {
        Horario::findOrFail($this->filas[1]['horario_id'])->update(['estado' => false]);
        $sem = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'No anterior', 'version' => '3.0']);
        $copia = Horario::findOrFail($this->filas[1]['horario_id'])->replicate();
        $copia->version_planificacion_id = $sem->id;
        $copia->estado = true;
        $copia->save();
        $datos = $this->consultar()->assertOk()->json();
        $this->assertNotContains($this->filas[1]['asignatura_id'], array_column($datos['asignaturas'], 'id'));
        $this->consultar(['version_id' => $sem->id])->assertJsonPath('seleccionados.version_id', $this->otraVersion->id);
    }

    public function test_validacion_y_permisos_se_aplican_al_endpoint(): void
    {
        $this->getJson(route('programacion.filtros', ['profesor_id' => ['1']]))->assertUnprocessable()->assertJsonValidationErrors('profesor_id');
        $this->consultar(['prioridad' => ['otro']])->assertUnprocessable()->assertJsonValidationErrors('prioridad.0');
        Auth::user()->revokePermissionTo('ver programacion anterior');
        $this->get(route('programacion.filtros'))->assertRedirect();
    }

    private function consultar(array $seleccion = [])
    {
        return $this->getJson(route('programacion.filtros', array_replace($this->contexto, $seleccion)));
    }

    private function comprobarOpciones(array $seleccion, array $datos): void
    {
        $claves = ['sede_id' => 'sedes', 'carrera_id' => 'carreras', 'semestre' => 'semestres', 'seccion_numero' => 'secciones', 'asignatura_id' => 'asignaturas', 'profesor_id' => 'profesores'];
        foreach ($claves as $campo => $clave) {
            $esperadas = collect($this->filas)->filter(function ($fila) use ($campo, $seleccion) {
                foreach ($seleccion as $otro => $valor) {
                    if ($otro !== $campo && (string) $fila[$otro] !== (string) $valor) return false;
                }
                return true;
            })->pluck($campo)->map(fn ($valor) => (string) $valor)->unique()->sort()->values()->all();
            $recibidas = collect($datos[$clave])->map(fn ($opcion) => (string) (is_array($opcion) ? $opcion['id'] : $opcion))->unique()->sort()->values()->all();
            $this->assertSame($esperadas, $recibidas, $campo.' con '.json_encode($seleccion));
        }
    }
}
