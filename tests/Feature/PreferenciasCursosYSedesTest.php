<?php

namespace Tests\Feature;

use App\Models;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class PreferenciasCursosYSedesTest extends IsolatedDatabaseTestCase
{
    private int $universidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = DB::table('universidades')->value('id');
        $usuario = Models\User::create(['name' => 'Gestión de prueba', 'rut' => '12345678', 'email' => 'reglas@example.test', 'password' => 'prueba']);
        foreach (['gestionar salas y recursos', 'gestionar conflictos', 'asignar horarios', 'ver panel inicio'] as $permiso) $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($usuario);
    }

    public function test_crea_regla_para_un_ramo_completo_y_rechaza_duplicados_aun_inactivos(): void
    {
        $curso = $this->curso('BACK', 'Backend');
        $tipo = Models\TipoSala::create(['nombre' => 'Laboratorio de computación']);
        $datos = $this->regla($curso, ['tipo_sala_id' => $tipo->id]);
        $this->post(route('prioridades.store'), $datos)->assertSessionHasNoErrors()->assertSessionHas('success');
        $regla = Models\PrioridadRecurso::sole();
        $this->assertSame($curso->id, $regla->asignatura_id);
        $this->assertSame('laboratorio', $regla->tipo_recurso);
        $this->assertSame(1, $regla->nivel);
        $this->get(route('prioridades.index'))->assertOk()->assertSee('BACK · Backend')->assertSee('Laboratorio de computación')->assertSee('Todas las secciones');
        $this->put(route('prioridades.update', $regla), array_replace($datos, ['nivel' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(2, $regla->fresh()->nivel);
        $this->post(route('prioridades.store'), $datos)->assertSessionHasErrors('asignatura_id');
        $regla->update(['estado' => false]);
        $this->post(route('prioridades.store'), $datos)->assertSessionHasErrors('asignatura_id');
        $this->assertDatabaseCount('prioridades_recursos', 1);
    }

    public function test_exige_curso_y_no_permite_referencias_incoherentes(): void
    {
        $curso = $this->curso('BACK', 'Backend');
        $tipo = Models\TipoSala::create(['nombre' => 'Computación']);
        $this->post(route('prioridades.store'), $this->regla($curso, ['asignatura_id' => null]))->assertSessionHasErrors('asignatura_id');
        $this->post(route('prioridades.store'), $this->regla($curso, ['tipo_recurso' => 'equipamiento', 'tipo_sala_id' => $tipo->id]))->assertSessionHasErrors('tipo_sala_id');
        $curso->update(['estado' => false]);
        $this->post(route('prioridades.store'), $this->regla($curso))->assertSessionHasErrors('asignatura_id');
        $this->assertDatabaseCount('prioridades_recursos', 0);
    }

    public function test_un_cruce_identifica_la_preferencia_en_todas_las_secciones_sin_reasignar_horarios(): void
    {
        $backend = $this->curso('BACK', 'Backend'); $otro = $this->curso('OTRO', 'Otro ramo');
        $sala = $this->sala('Talcahuano', 'LAB-1');
        $lab = Models\Laboratorio::create(['sala_id' => $sala->id, 'nombre' => 'Computación', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $version = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'nombre' => 'Semestral', 'version' => 0.1, 'tipo' => 'semestral', 'estado' => 'Borrador', 'es_actual' => true, 'usuario_id' => auth()->id()]);
        $this->post(route('prioridades.store'), $this->regla($backend))->assertSessionHasNoErrors();
        $this->post(route('prioridades.store'), $this->regla($otro, ['nivel' => 2]))->assertSessionHasNoErrors();
        foreach ([[$backend, '1'], [$backend, '2'], [$otro, '1']] as [$curso, $codigo]) {
            $seccion = Models\Seccion::create(['asignatura_id' => $curso->id, 'periodo_academico_id' => $periodo->id, 'codigo' => $codigo]);
            $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Laboratorio', 'cantidad_horas' => 1]);
            $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
            $h = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id, 'version_planificacion_id' => $version->id,
                'dia_id' => DB::table('dias')->orderBy('numero')->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->orderBy('numero')->value('id'), 'estado' => true]);
            Models\AsignacionLaboratorio::create(['horario_id' => $h->id, 'laboratorio_id' => $lab->id, 'estado' => true]);
        }
        $antes = Models\AsignacionLaboratorio::orderBy('id')->get()->toArray();
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $preferencias = $reporte['observaciones']->where('tipo', 'conflicto')->filter(fn ($f) => str_contains($f['mensaje'], 'BACK · Backend (nivel 1) tiene prioridad sobre OTRO · Otro ramo (nivel 2)'));
        $this->assertCount(2, $preferencias);
        $this->assertSame($antes, Models\AsignacionLaboratorio::orderBy('id')->get()->toArray());
        $reglaBackend = Models\PrioridadRecurso::where('asignatura_id', $backend->id)->firstOrFail();
        $computadores = Models\Equipamiento::create($this->equipo(['cantidad' => 30]));
        $this->put(route('prioridades.update', $reglaBackend), $this->regla($backend, ['tipo_equipamiento' => 'Computación']))->assertSessionHasNoErrors();
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $this->assertFalse($reporte['observaciones']->contains(fn ($f) => str_contains($f['mensaje'], 'BACK · Backend (nivel 1) tiene prioridad')));
        Models\EquipamientoEspacio::create(['equipamiento_id' => $computadores->id, 'sala_id' => $sala->id, 'cantidad' => 30]);
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $this->assertCount(2, $reporte['observaciones']->where('tipo', 'conflicto')->filter(fn ($f) => str_contains($f['mensaje'], 'BACK · Backend (nivel 1) tiene prioridad')));
        Models\PrioridadRecurso::query()->update(['nivel' => 1]);
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $this->assertTrue($reporte['observaciones']->contains(fn ($f) => str_contains($f['mensaje'], 'comparten nivel 1')));
        Models\PrioridadRecurso::query()->update(['estado' => false]);
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $this->assertFalse($reporte['observaciones']->contains(fn ($f) => str_contains($f['mensaje'], 'Preferencia de uso')));
    }

    public function test_registra_equipos_en_dos_sedes_en_una_sola_operacion_sin_multiplicar_stock(): void
    {
        $a = $this->sala('Talcahuano', 'A-1'); $b = $this->sala('Concepción', 'B-1');
        $datos = $this->equipo(['ubicaciones' => [
            ['sede_id' => $a->sede_id, 'espacio' => 'sala:'.$a->id, 'cantidad' => 3],
            ['sede_id' => $b->sede_id, 'espacio' => 'sala:'.$b->id, 'cantidad' => 2],
        ]]);
        $this->get(route('recursos.create'))->assertOk()->assertSee('Distribución inicial por sede')->assertSee('Talcahuano')->assertSee('Concepción');
        $this->post(route('recursos.store'), $datos)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('equipamientos', 1);
        $this->assertSame(5, Models\Equipamiento::sole()->cantidad);
        $this->assertSame(5, (int) Models\EquipamientoEspacio::sum('cantidad'));
        $this->assertDatabaseCount('equipamientos_espacios', 2);
        $this->assertDatabaseCount('historial_acciones', 3);
    }

    public function test_distribucion_invalida_revierte_todo_el_registro(): void
    {
        $sala = $this->sala('Talcahuano', 'A-1');
        foreach ([
            [['espacio' => 'sala:'.$sala->id, 'cantidad' => 6]],
            [['espacio' => 'sala:'.$sala->id, 'cantidad' => 1], ['espacio' => 'sala:'.$sala->id, 'cantidad' => 1]],
            [['sede_id' => $sala->sede_id, 'cantidad' => 2]],
        ] as $ubicaciones) {
            $this->post(route('recursos.store'), $this->equipo(['ubicaciones' => $ubicaciones]))->assertSessionHasErrors();
            $this->assertDatabaseCount('equipamientos', 0);
            $this->assertDatabaseCount('equipamientos_espacios', 0);
            $this->assertDatabaseCount('historial_acciones', 0);
        }
        $otraSede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Otra sede', 'estado' => true]);
        $this->post(route('recursos.store'), $this->equipo(['ubicaciones' => [['sede_id' => $otraSede->id, 'espacio' => 'sala:'.$sala->id, 'cantidad' => 1]]]))->assertSessionHasErrors();
        $otraInstitucion = Models\Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA', 'estado' => true]);
        $sala->update(['universidad_id' => $otraInstitucion->id]);
        $this->post(route('recursos.store'), $this->equipo(['ubicaciones' => [['espacio' => 'sala:'.$sala->id, 'cantidad' => 1]]]))->assertSessionHasErrors();
        $this->assertDatabaseCount('equipamientos', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_oculta_etiquetas_de_importacion_y_conserva_las_relaciones_de_las_salas(): void
    {
        $sala = $this->sala('Talcahuano', 'A-1');
        $sala->tipoSala->update(['nombre' => 'Recurso importado']);
        $sala->update(['descripcion' => 'Recurso importado desde programación Excel.']);
        $this->get(route('salas.create'))->assertOk()->assertDontSee('Recurso importado')->assertDontSee('Tipo de sala *')->assertSee('type="hidden" name="tipo_sala_id"', false);
        $this->get(route('salas.edit', $sala))->assertOk()->assertDontSee('Recurso importado')->assertDontSee('Tipo de sala *');
        $this->get(route('salas.index'))->assertOk()->assertDontSee('Recurso importado');
        $this->assertDatabaseHas('tipos_sala', ['id' => $sala->tipo_sala_id, 'nombre' => 'Recurso importado']);
    }

    private function curso(string $codigo, string $nombre): Models\Asignatura
    {
        return Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => $codigo, 'nombre' => $nombre, 'estado' => true]);
    }

    private function regla(Models\Asignatura $curso, array $datos = []): array
    {
        return array_replace(['alcance' => 'curso', 'asignatura_id' => $curso->id, 'tipo_recurso' => 'laboratorio', 'nivel' => 1, 'estado' => 1], $datos);
    }

    private function sala(string $nombre, string $codigo): Models\Sala
    {
        $sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => $nombre, 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Sala de docencia', 'estado' => true]);
        return Models\Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => $codigo, 'capacidad' => 40, 'estado' => true, 'disponible' => true]);
    }

    private function equipo(array $datos = []): array
    {
        return array_replace(['universidad_id' => $this->universidad, 'nombre' => 'Computadores', 'tipo' => 'Computación', 'cantidad' => 5, 'estado' => 1, 'disponible' => 1], $datos);
    }
}
