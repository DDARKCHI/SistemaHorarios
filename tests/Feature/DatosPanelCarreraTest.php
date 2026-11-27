<?php

namespace Tests\Feature;

use App\Models;
use App\Services\DatosPanelCarrera;
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class DatosPanelCarreraTest extends IsolatedDatabaseTestCase
{
    private int $universidad;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Carrera $carrera;
    private Models\Carrera $otraCarrera;
    private Models\Sede $sede;
    private Models\Profesor $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = DB::table('universidades')->value('id');
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'version' => 0.1, 'nombre' => 'Base de prueba', 'estado' => 'Borrador']);
        $this->sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $this->universidad, 'codigo' => 'INF', 'nombre' => 'Informática', 'sede_id' => $this->sede->id]);
        $this->otraCarrera = Models\Carrera::create(['universidad_id' => $this->universidad, 'codigo' => 'RED', 'nombre' => 'Redes', 'sede_id' => $this->sede->id]);
        $this->docente = Models\Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Ana', 'apellido' => 'Prueba']);
    }

    public function test_separa_carga_por_grupo_y_version_y_usa_docente_efectivo(): void
    {
        $seccion = $this->seccion($this->carrera, 'BACK');
        $seccion->profesores()->create(['profesor_id' => $this->docente->id, 'estado' => true, 'es_principal' => true]);
        $catedra = $this->grupo($seccion, 'Cátedra', 2);
        $laboratorio = $this->grupo($seccion, 'Laboratorio', 1);
        $modulo = $this->modulo($catedra, 1);
        $this->modulo($laboratorio, 2)->update(['estado' => false]);
        $otraVersion = $this->version->replicate(['version']);
        $otraVersion->version = 0.2;
        $otraVersion->save();
        $moduloRama = $this->modulo($catedra, 3);
        $moduloRama->update(['version_planificacion_id' => $otraVersion->id]);
        $solicitud = Models\SolicitudDisponibilidad::create(['periodo_academico_id' => $this->periodo->id, 'profesor_id' => $this->docente->id, 'estado' => 'Aprobada']);
        Models\RespuestaDisponibilidad::create(['solicitud_disponibilidad_id' => $solicitud->id, 'profesor_id' => $this->docente->id, 'respuesta' => 'Aprobada']);
        Models\ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $this->docente->id, 'nombre' => 'Ana', 'apellido' => 'Registro histórico']);

        $datos = $this->consultar();

        $this->assertSame([$modulo->id], array_column($datos['horarios'], 'id'));
        $this->assertSame('Ana Registro histórico', $datos['horarios'][0]['docentes'][0]['nombre']);
        $this->assertSame(3, $datos['indicadores']['requeridos']);
        $this->assertSame(2, $datos['indicadores']['pendientes']);
        $this->assertSame(1, $datos['indicadores']['con_docente']);
        $this->assertSame(1, $datos['indicadores']['disponibilidad_aprobada']);
        $this->assertSame(0, $datos['indicadores']['carga_completa']);
        $this->assertSame(['Cátedra' => 1, 'Laboratorio' => 1], array_column($datos['indicadores']['por_actividad'], 'pendientes', 'tipo'));
    }

    public function test_carga_docente_considera_otra_carrera_y_laboratorio_reserva_sala_fisica(): void
    {
        $grupoPropio = $this->grupo($this->seccion($this->carrera, 'BACK'), 'Laboratorio', 1);
        $grupoExterno = $this->grupo($this->seccion($this->otraCarrera, 'RED1'), 'Cátedra', 1);
        foreach ([$grupoPropio, $grupoExterno] as $grupo) {
            $grupo->profesores()->create(['profesor_id' => $this->docente->id, 'estado' => true, 'es_principal' => true]);
        }
        $propio = $this->modulo($grupoPropio, 1);
        $externo = $this->modulo($grupoExterno, 3);
        $tipo = Models\TipoSala::create(['nombre' => 'Laboratorio', 'estado' => true]);
        $sala = Models\Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $this->sede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'LAB-01', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        $laboratorio = Models\Laboratorio::create(['sala_id' => $sala->id, 'nombre' => 'Computación', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        $propio->asignacionesLaboratorios()->create(['laboratorio_id' => $laboratorio->id, 'estado' => true]);
        $externo->asignacionesSalas()->create(['sala_id' => $sala->id, 'estado' => true]);
        Models\DisponibilidadProfesor::create(['periodo_academico_id' => $this->periodo->id, 'profesor_id' => $this->docente->id,
            'dia_id' => $propio->dia_id, 'bloque_horario_id' => $propio->bloque_horario_id, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);

        $datos = $this->consultar();
        $docente = $datos['docentes'][0];
        $externa = $externo->dia_id.':'.$externo->bloque_horario_id;
        $fijo = $datos['laboratorios'][0];

        $this->assertSame([$propio->id], array_column($datos['horarios'], 'id'));
        $this->assertSame([$propio->id], $docente['horarios']);
        $this->assertSame(2, $docente['modulos']);
        $this->assertSame(1, $docente['huecos']);
        $this->assertSame(1, $docente['dias']);
        $this->assertSame(1, $docente['fuera']);
        $this->assertSame([$externa => 1], $docente['ocupaciones_externas']);
        $this->assertSame('Computación', $datos['horarios'][0]['espacios'][0]['nombre']);
        $this->assertSame(2, $fijo['ocupados']);
        $this->assertSame($fijo['total'] - 2, $fijo['libres']);
        $this->assertSame(['horarios' => [], 'ocupado' => true, 'otras_carreras' => 1], $fijo['bloques'][$externa]);

        $externo->update(['bloque_horario_id' => $propio->bloque_horario_id]);
        $datos = $this->consultar();
        $cruces = collect($datos['observaciones'])->where('tipo', 'conflicto');
        $this->assertCount(1, $cruces);
        $this->assertSame([$propio->id], $cruces->first()['horarios']);
        $this->assertSame('rojo', $datos['horarios'][0]['nivel']);
    }

    public function test_marca_cruce_de_cohorte_y_omite_paralelos_del_mismo_ramo(): void
    {
        $seccion = $this->seccion($this->carrera, 'BACK');
        $grupo = $this->grupo($seccion, 'Laboratorio', 1);
        $paralelo = $grupo->actividadSeccion->grupos()->create(['codigo' => '2', 'numero' => 2, 'estado_activo' => true]);
        $this->modulo($grupo, 1);
        $this->modulo($paralelo, 1);

        $this->assertSame(0, $this->consultar()['indicadores']['coincidencias']);

        $this->modulo($this->grupo($this->seccion($this->carrera, 'FRONT'), 'Cátedra', 1), 1);
        $datos = $this->consultar();

        $this->assertSame(2, $datos['indicadores']['coincidencias']);
        $this->assertSame(['rojo', 'rojo', 'rojo'], array_column($datos['horarios'], 'nivel'));
    }

    public function test_la_carga_del_ramo_sin_actividad_preparada_cuenta_como_pendiente(): void
    {
        $seccion = $this->seccion($this->carrera, 'BACK');
        $seccion->asignatura()->update(['horas_catedra' => 1, 'horas_laboratorio' => 2]);
        $grupo = $this->grupo($seccion, 'Cátedra', 1);
        $grupo->profesores()->create(['profesor_id' => $this->docente->id, 'es_principal' => true, 'estado' => true]);
        $this->modulo($grupo, 1);

        $datos = $this->consultar();

        $this->assertSame(3, $datos['indicadores']['requeridos']);
        $this->assertSame(2, $datos['indicadores']['pendientes']);
        $this->assertFalse($datos['secciones'][0]['carga_completa']);
        $this->assertSame('Laboratorio', $datos['secciones'][0]['carga_sin_actividad'][0]['actividad']);
        $this->assertCount(1, $datos['secciones'][0]['grupos']);
    }

    private function seccion(Models\Carrera $carrera, string $codigo): Models\Seccion
    {
        $ramo = Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => $codigo, 'nombre' => $codigo]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $ramo->id, 'codigo' => '1', 'semestre' => 2]);
        $seccion->carreras()->attach($carrera);

        return $seccion;
    }

    private function grupo(Models\Seccion $seccion, string $tipo, int $modulos): Models\GrupoSeccion
    {
        $actividad = $seccion->actividades()->create(['tipo' => $tipo, 'cantidad_horas' => $modulos, 'estado' => true]);

        return $actividad->grupos()->create(['codigo' => '1', 'numero' => 1, 'estado_activo' => true]);
    }

    private function modulo(Models\GrupoSeccion $grupo, int $bloque): Models\Horario
    {
        return Models\Horario::create(['actividad_seccion_id' => $grupo->actividad_seccion_id, 'grupo_seccion_id' => $grupo->id,
            'version_planificacion_id' => $this->version->id, 'dia_id' => Models\Dia::where('numero', 1)->value('id'),
            'bloque_horario_id' => Models\BloqueHorario::where('numero', $bloque)->value('id'), 'estado' => true]);
    }

    private function consultar(): array
    {
        return app(DatosPanelCarrera::class)->consultar($this->version, $this->carrera);
    }
}
