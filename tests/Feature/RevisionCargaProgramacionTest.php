<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\IndicadoresProgramacion;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class RevisionCargaProgramacionTest extends IsolatedDatabaseTestCase
{
    private PeriodoAcademico $periodo;
    private VersionPlanificacion $version;
    private ActividadSeccion $actividad;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $usuario = User::create(['name' => 'Revisión de carga', 'rut' => '74444444', 'email' => 'carga@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar conflictos', 'web'));
        $this->actingAs($usuario);
        $this->periodo = PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'es_actual' => true]);
        $asignatura = Asignatura::create(['universidad_id' => $universidad, 'codigo' => 'CAR-1', 'nombre' => 'Programación']);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '11']);
        $this->actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3]);
    }

    public function test_los_excesos_de_un_grupo_no_compensan_los_pendientes_de_otro(): void
    {
        $primero = $this->grupo('G:1'); $this->grupo('G:2');
        foreach (range(1, 5) as $dia) $this->modulo($primero, $dia);
        $carga = $this->carga();
        $this->assertSame([6, 5, 3, 3, 2], $this->medidas($carga));
        $this->assertCount(2, $carga['observaciones']);
        $this->assertSame(['G:1', 'G:2'], array_column(array_column($carga['observaciones'], 'clase'), 'grupo'));
        $this->assertStringContainsString('Hay 2 en exceso', $carga['observaciones'][0]['mensaje']);
        $this->assertStringContainsString('Faltan 3 por asignar', $carga['observaciones'][1]['mensaje']);
        $indicadores = app(IndicadoresProgramacion::class)->calcular($this->periodo, $this->version);
        $this->assertSame($indicadores['graficos']['horario']['asignados'], $carga['cubiertos']);
        $this->assertSame($indicadores['graficos']['horario']['total'], $carga['requeridos']);
    }

    public function test_un_grupo_sin_horario_aparece_aunque_la_version_no_tenga_modulos(): void
    {
        $this->grupo('G:1');
        $this->assertSame([3, 0, 0, 3, 0], $this->medidas($this->carga()));
        $this->get($this->url())->assertOk()->assertSee('Módulos pendientes')->assertSee('CAR-1')
            ->assertDontSee('Esta versión no tiene módulos activos')->assertDontSee('Sin observaciones detectadas');
    }

    public function test_una_carga_exacta_no_genera_observaciones_de_carga(): void
    {
        $grupo = $this->grupo('G:1');
        foreach (range(1, 3) as $dia) $this->modulo($grupo, $dia);
        $this->assertSame([3, 3, 3, 0, 0], $this->medidas($this->carga()));
        $this->assertSame([], $this->carga()['observaciones']);
        $this->get($this->url())->assertOk()->assertSee('Sin observaciones para este filtro')->assertDontSee('Los módulos requeridos están incompletos');
    }

    public function test_cada_actividad_mantiene_su_propia_carga_aunque_el_codigo_de_grupo_se_repita(): void
    {
        $catedra = $this->grupo('G:1');
        $lab = ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Laboratorio', 'cantidad_horas' => 2]);
        $laboratorio = $this->grupo('G:1', $lab);
        foreach (range(1, 3) as $dia) $this->modulo($catedra, $dia);
        $this->modulo($laboratorio, 4);
        $carga = $this->carga();
        $this->assertSame([5, 4, 4, 1, 0], $this->medidas($carga));
        $this->assertCount(1, $carga['observaciones']);
        $this->assertSame('Laboratorio', $carga['observaciones'][0]['clase']['actividad']);
    }

    public function test_ignora_modulos_inactivos_y_los_de_otras_versiones(): void
    {
        $grupo = $this->grupo('G:1');
        $this->modulo($grupo, 1);
        $this->modulo($grupo, 2)->update(['estado' => false]);
        $otra = $this->version->replicate(); $otra->es_actual = false; $otra->save();
        $this->modulo($grupo, 3)->update(['version_planificacion_id' => $otra->id]);
        $this->assertSame([3, 1, 1, 2, 0], $this->medidas($this->carga()));
        $this->get(route('programacion.validacion', ['version_id' => $otra->id, 'tipo' => 'carga']))->assertOk()
            ->assertViewHas('reporte', fn ($r) => $r['carga']['asignados'] === 1 && $r['carga']['pendientes'] === 2);
    }

    public function test_las_actividades_y_secciones_inactivas_y_otros_periodos_no_se_exigen(): void
    {
        $this->grupo('G:1');
        $inactiva = ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Taller', 'cantidad_horas' => 8, 'estado' => false]);
        $this->grupo('G:1', $inactiva);
        $seccion = $this->actividad->seccion->replicate(); $seccion->codigo = '22'; $seccion->estado_activo = false; $seccion->save();
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 8]);
        $this->grupo('G:1', $actividad);
        $otro = $this->periodo->replicate(); $otro->nombre = '2025-2'; $otro->anio = 2025; $otro->save();
        $seccion = $this->actividad->seccion->replicate(); $seccion->periodo_academico_id = $otro->id; $seccion->save();
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 8]);
        $this->grupo('G:1', $actividad);
        $this->assertSame([3, 0, 0, 3, 0], $this->medidas($this->carga()));
        $this->assertSame(1, $this->carga()['actividades']);
    }

    public function test_una_actividad_sin_grupos_y_una_carga_cero_quedan_pendientes_de_revision(): void
    {
        $carga = $this->carga();
        $this->assertSame(1, $carga['actividades_sin_grupos']);
        $this->assertStringContainsString('No se puede dar su carga por cubierta', $carga['observaciones'][0]['mensaje']);
        $grupo = $this->grupo('G:1'); $this->actividad->update(['cantidad_horas' => 0]); $this->modulo($grupo, 1);
        $carga = $this->carga();
        $this->assertSame([0, 1, 0, 0, 0], $this->medidas($carga));
        $this->assertSame(1, $carga['grupos_sin_carga']);
        $this->assertStringContainsString('define la carga', $carga['observaciones'][0]['mensaje']);
        $this->get($this->url())->assertOk()->assertDontSee('Sin observaciones para este filtro')->assertSee('Los módulos requeridos están incompletos');
    }

    public function test_un_grupo_inactivo_no_aumenta_la_carga_ni_cubre_modulos(): void
    {
        $grupo = $this->grupo('G:1'); $grupo->update(['estado_activo' => false]);
        $this->modulo($grupo, 1);
        $carga = $this->carga();
        $this->assertSame([0, 0, 0, 0, 0], $this->medidas($carga));
        $this->assertSame(1, $carga['actividades_sin_grupos']);
        $this->assertSame(1, $carga['modulos_sin_grupo']);
        $this->assertCount(2, $carga['observaciones']);
    }

    public function test_un_modulo_sin_grupo_o_con_grupo_de_otra_actividad_no_cubre_la_carga(): void
    {
        $grupo = $this->grupo('G:1');
        $this->modulo($grupo, 1)->update(['grupo_seccion_id' => null]);
        $otra = ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Laboratorio', 'cantidad_horas' => 2]);
        $grupoLab = $this->grupo('G:1', $otra);
        $this->modulo($grupoLab, 2)->update(['actividad_seccion_id' => $this->actividad->id]);
        $carga = $this->carga();
        $this->assertSame([5, 0, 0, 5, 0], $this->medidas($carga));
        $this->assertSame(2, $carga['modulos_sin_grupo']);
        $this->assertCount(4, $carga['observaciones']);
    }

    public function test_los_modulos_de_actividades_inactivas_no_se_ocultan_como_carga_cubierta(): void
    {
        $this->modulo($this->grupo('G:1'), 1);
        $this->actividad->update(['estado' => false]);
        $carga = $this->carga();
        $this->assertSame(0, $carga['asignados']);
        $this->assertStringContainsString('actividad o sección inactiva', $carga['observaciones'][0]['mensaje']);
    }

    public function test_un_modulo_con_varios_docentes_y_salas_solo_se_cuenta_una_vez(): void
    {
        $h = $this->modulo($this->grupo('G:1'), 1);
        $sede = Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Sede']);
        $tipo = TipoSala::create(['nombre' => 'Aula']);
        foreach ([1, 2] as $n) {
            $profesor = Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Docente '.$n, 'apellido' => 'De prueba']);
            HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $profesor->id]);
            $sala = Sala::create(['universidad_id' => $this->periodo->universidad_id, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-'.$n]);
            AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $sala->id]);
        }
        $this->assertSame([3, 1, 1, 2, 0], $this->medidas($this->carga()));
    }

    public function test_el_filtro_de_cursos_incluye_la_carga_y_no_contamina_los_de_recursos(): void
    {
        $this->grupo('G:1');
        $this->get($this->url(['tipo' => 'curso']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        foreach (['sala', 'profesor', 'laboratorio', 'movil'] as $tipo) {
            $this->get($this->url(['tipo' => $tipo]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 0);
        }
    }

    public function test_pagina_los_grupos_sin_horario_y_conserva_version_y_tipo(): void
    {
        foreach (range(1, 21) as $n) $this->grupo('G:'.$n);
        $this->get($this->url())->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 21 && $p->count() === 20)->assertSee('Siguiente');
        $this->get($this->url(['page' => 2]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->count() === 1
            && str_contains($p->previousPageUrl(), 'tipo=carga') && str_contains($p->previousPageUrl(), 'version_id='.$this->version->id));
    }

    public function test_la_revision_no_modifica_la_carga_ni_las_asignaciones(): void
    {
        $this->modulo($this->grupo('G:1'), 1);
        $tablas = ['actividades_seccion', 'grupos_seccion', 'horarios', 'versiones_planificacion', 'validaciones_horario', 'observaciones_validacion', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $this->get($this->url())->assertOk()->assertSee('Cumplimiento de módulos')->assertSee('Faltan 2 por asignar')
            ->assertSee('sin convertirlos a horas de 60 minutos');
        $this->assertSame($antes, $snapshot());
    }

    private function grupo(string $codigo, ?ActividadSeccion $actividad = null): GrupoSeccion
    {
        return GrupoSeccion::create(['actividad_seccion_id' => ($actividad ?? $this->actividad)->id, 'codigo' => $codigo]);
    }

    private function modulo(GrupoSeccion $grupo, int $dia): Horario
    {
        return Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $grupo->actividad_seccion_id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => DB::table('dias')->where('numero', $dia)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id')]);
    }

    private function carga(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version)['carga']; }
    private function medidas(array $carga): array { return array_map(fn ($clave) => $carga[$clave], ['requeridos', 'asignados', 'cubiertos', 'pendientes', 'excedentes']); }
    private function url(array $filtros = []): string { return route('programacion.validacion', $filtros + ['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id, 'tipo' => 'carga']); }
}
