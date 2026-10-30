<?php

namespace Tests\Feature;

use App\Models;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class RequerimientosActividadTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\ActividadSeccion $actividad;
    private Models\VersionPlanificacion $version;
    private Models\TipoSala $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Configuración académica', 'rut' => '76666666', 'email' => 'requerimientos@example.test', 'password' => 'prueba']);
        foreach (['gestionar conflictos', 'asignar horarios', 'ver programacion semestral'] as $permiso) $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($this->usuario);
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $universidad, 'codigo' => 'REQ-1', 'nombre' => 'Programación avanzada']);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $periodo->id, 'codigo' => '11']);
        $this->actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3]);
        $this->tipo = Models\TipoSala::create(['nombre' => 'Aula']);
    }

    public function test_lista_actividades_y_sus_reglas_sin_escribir_datos(): void
    {
        $r = $this->regla(); $r->update(['estado' => false]);
        $this->get($this->indice())->assertOk()->assertSee('Requerimientos de actividades')->assertSee('REQ-1')->assertSee('Inactivo')
            ->assertSee('Agregar requerimiento')->assertSee('Los requerimientos se comparten entre las versiones');
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->get($this->crear())->assertOk()->assertSee('Guardar requerimiento')->assertSee('Tipo de sala')->assertSee('todas las versiones del período');
    }

    public function test_registra_un_requerimiento_y_su_historial_con_el_contexto_del_periodo(): void
    {
        $this->post($this->guardar(), $this->datos() + ['actividad_seccion_id' => 999999])->assertRedirect($this->indice())->assertSessionHas('success');
        $r = Models\RequerimientoActividad::sole();
        $this->assertSame($this->actividad->id, $r->actividad_seccion_id);
        $this->assertSame('Sala', $r->tipo_requerimiento);
        $this->assertSame(2, $r->cantidad);
        $log = DB::table('historial_acciones')->sole();
        $datos = json_decode($log->datos, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Crear requerimiento', $log->accion);
        $this->assertNull($datos['antes']);
        $this->assertSame($this->version->periodo_academico_id, $datos['periodo_id']);
        $this->assertSame($this->actividad->id, $datos['despues']['actividad_seccion_id']);
    }

    public function test_edita_desactiva_y_reactiva_sin_eliminar_la_regla_ni_cambiar_horarios(): void
    {
        $r = $this->regla();
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:1']);
        $h = Models\Horario::create(['actividad_seccion_id' => $this->actividad->id, 'grupo_seccion_id' => $grupo->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => 1, 'bloque_horario_id' => 1]);
        $antes = $h->fresh()->getAttributes();
        $this->assertSame(1, app(RevisionConflictosProgramacion::class)->revisar($this->version)['recursos']);
        $this->put($this->actualizar($r), $this->datos(['estado' => 0]))->assertRedirect($this->indice());
        $this->assertFalse($r->fresh()->estado);
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($this->version)['recursos']);
        $this->put($this->actualizar($r), $this->datos(['estado' => 1, 'cantidad' => 3]))->assertRedirect($this->indice());
        $this->assertTrue($r->fresh()->estado);
        $this->assertSame(3, $r->fresh()->cantidad);
        $this->assertSame($antes, $h->fresh()->getAttributes());
        $this->assertDatabaseCount('requerimientos_actividad', 1);
        $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_guardar_sin_cambios_no_duplica_el_historial(): void
    {
        $this->post($this->guardar(), $this->datos())->assertRedirect();
        $r = Models\RequerimientoActividad::sole();
        $this->put($this->actualizar($r), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_datos_invalidos_sin_cambios_parciales(string $campo, mixed $valor): void
    {
        $this->post($this->guardar(), $this->datos([$campo => $valor]))->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('requerimientos_actividad', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function entradasInvalidas(): array
    {
        return [['tipo_requerimiento', 'Desconocido'], ['tipo_requerimiento', ['Sala']], ['cantidad', 0], ['cantidad', 65536], ['cantidad', 1.5],
            ['capacidad_minima', 0], ['capacidad_minima', -1], ['tipo_sala_id', 999999], ['tipo_sala_id', [1]], ['prioridad_recurso_id', 999999],
            ['estado', 'otro'], ['obligatorio', 'otro'], ['observaciones', str_repeat('a', 2001)], ['nombre_recurso', str_repeat('a', 101)]];
    }

    public function test_el_movil_exige_tipo_de_sala_y_equipamiento_exige_nombre(): void
    {
        $this->post($this->guardar(), $this->datos(['tipo_requerimiento' => 'Laboratorio móvil', 'tipo_sala_id' => null]))->assertSessionHasErrors('tipo_sala_id');
        $this->post($this->guardar(), $this->datos(['tipo_requerimiento' => 'Equipamiento', 'tipo_sala_id' => null, 'nombre_recurso' => null]))->assertSessionHasErrors('nombre_recurso');
        $this->post($this->guardar(), $this->datos(['tipo_requerimiento' => 'Equipamiento', 'nombre_recurso' => 'Proyector']))->assertSessionHasErrors('tipo_sala_id');
        $this->post($this->guardar(), $this->datos(['tipo_requerimiento' => 'Equipamiento', 'nombre_recurso' => 'Proyector', 'tipo_sala_id' => null]))->assertRedirect();
        $this->assertSame('Equipamiento', Models\RequerimientoActividad::sole()->tipo_requerimiento);
    }

    public function test_no_permite_adoptar_referencias_inactivas_pero_conserva_las_actuales_al_editar(): void
    {
        $prioridad = Models\PrioridadRecurso::create(['nombre' => 'Preferente', 'estado' => false]);
        $this->tipo->update(['estado' => false]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors('tipo_sala_id');
        $r = $this->regla(['prioridad_recurso_id' => $prioridad->id]);
        $this->get($this->editar($r))->assertOk()->assertSee('Aula (inactivo)')->assertSee('Preferente (inactiva)');
        $this->put($this->actualizar($r), $this->datos(['prioridad_recurso_id' => $prioridad->id]))->assertRedirect();
        $otra = Models\TipoSala::create(['nombre' => 'Taller', 'estado' => false]);
        $this->put($this->actualizar($r), $this->datos(['tipo_sala_id' => $otra->id]))->assertSessionHasErrors('tipo_sala_id');
        $this->assertSame($this->tipo->id, $r->fresh()->tipo_sala_id);
    }

    public function test_los_tipos_anteriores_pueden_conservarse_y_los_alias_se_normalizan(): void
    {
        $r = $this->regla(['tipo_requerimiento' => 'Laboratorio de química']);
        $this->get($this->editar($r))->assertOk()->assertSee('Laboratorio de química (tipo anterior)');
        $this->put($this->actualizar($r), $this->datos(['tipo_requerimiento' => 'Laboratorio de química']))->assertRedirect();
        $this->assertSame('Laboratorio de química', $r->fresh()->tipo_requerimiento);
        $r->update(['tipo_requerimiento' => ' laboratorios fijos ']);
        $this->put($this->actualizar($r), $this->datos(['tipo_requerimiento' => 'laboratorios fijos']))->assertRedirect();
        $this->assertSame('Laboratorio', $r->fresh()->tipo_requerimiento);
    }

    public function test_los_dos_permisos_se_exigen_en_las_mutaciones_y_la_consulta_sigue_disponible(): void
    {
        $r = $this->regla();
        $this->usuario->revokePermissionTo('asignar horarios');
        $this->get($this->indice())->assertOk()->assertDontSee('Agregar requerimiento')->assertDontSee('Editar requerimiento '.$r->id);
        $this->get($this->crear())->assertRedirect();
        $this->post($this->guardar(), $this->datos())->assertRedirect();
        $this->put($this->actualizar($r), $this->datos())->assertRedirect();
        $this->usuario->givePermissionTo('asignar horarios'); $this->usuario->revokePermissionTo('gestionar conflictos');
        $this->get($this->indice())->assertRedirect();
        $this->post($this->guardar(), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('requerimientos_actividad', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
        auth()->logout(); $this->get($this->indice())->assertRedirect(route('login'));
    }

    public function test_el_anidamiento_impide_editar_un_requerimiento_de_otra_actividad(): void
    {
        $otra = Models\ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Laboratorio']);
        $r = $this->regla(['actividad_seccion_id' => $otra->id]);
        $this->get($this->editar($r))->assertNotFound();
        $this->put($this->actualizar($r), $this->datos())->assertNotFound();
        $this->assertSame($otra->id, $r->fresh()->actividad_seccion_id);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('padresInactivos')]
    public function test_un_contexto_inactivo_solo_se_puede_consultar(string $padre): void
    {
        $r = $this->regla();
        match ($padre) {
            'actividad' => $this->actividad->update(['estado' => false]),
            'seccion' => $this->actividad->seccion->update(['estado_activo' => false]),
            'periodo' => $this->actividad->seccion->periodoAcademico->update(['estado' => false]),
        };
        $this->get($this->indice())->assertOk()->assertSee('Solo consulta')->assertDontSee('Agregar requerimiento');
        $this->get($this->crear())->assertForbidden();
        $this->post($this->guardar(), $this->datos())->assertForbidden();
        $this->put($this->actualizar($r), $this->datos())->assertForbidden();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function padresInactivos(): array { return [['actividad'], ['seccion'], ['periodo']]; }

    public function test_rechaza_una_version_de_regreso_ajena_o_de_programacion_anterior(): void
    {
        $anterior = $this->version->replicate(); $anterior->tipo = 'anterior'; $anterior->save();
        $this->get($this->indice(['version_id' => $anterior->id]))->assertRedirect()->assertSessionHasErrors('version_id');
        $this->post($this->guardar(), $this->datos(['version_id' => $anterior->id]))->assertSessionHasErrors('version_id');
        $otroPeriodo = $this->actividad->seccion->periodoAcademico->replicate(); $otroPeriodo->nombre = '2025-2'; $otroPeriodo->anio = 2025; $otroPeriodo->save();
        $otra = $this->version->replicate(); $otra->periodo_academico_id = $otroPeriodo->id; $otra->save();
        $this->post($this->guardar(), $this->datos(['version_id' => $otra->id]))->assertSessionHasErrors('version_id');
        $this->assertDatabaseCount('requerimientos_actividad', 0);
    }

    public function test_busca_literalmente_y_pagina_conservando_periodo_y_version(): void
    {
        for ($i = 0; $i < 11; $i++) Models\ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Taller '.$i]);
        $this->get($this->indice())->assertOk()->assertViewHas('actividades', fn ($p) => $p->total() === 12 && $p->count() === 10);
        $this->get($this->indice(['page' => 2]))->assertOk()->assertViewHas('actividades', fn ($p) => $p->count() === 2 && str_contains($p->previousPageUrl(), 'version_id='.$this->version->id));
        $this->get($this->indice(['q' => 'Programación']))->assertOk()->assertViewHas('actividades', fn ($p) => $p->total() === 12);
        $this->get($this->indice(['q' => '%']))->assertOk()->assertViewHas('actividades', fn ($p) => $p->total() === 0);
        $this->get($this->indice(['q' => ['REQ']]))->assertRedirect()->assertSessionHasErrors('q');
        $this->get($this->indice(['page' => 99]))->assertRedirect();
    }

    public function test_un_error_de_historial_revierte_la_escritura_del_requerimiento(): void
    {
        $this->withoutExceptionHandling();
        Models\RequerimientoActividad::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try {
            $this->post($this->guardar(), $this->datos());
            $this->fail('El error del historial debe impedir el guardado.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('historial_acciones', $error->getMessage());
        }
        $this->assertDatabaseCount('requerimientos_actividad', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    private function regla(array $cambios = []): Models\RequerimientoActividad
    {
        return Models\RequerimientoActividad::create($cambios + ['actividad_seccion_id' => $this->actividad->id, 'tipo_requerimiento' => 'Sala', 'cantidad' => 1, 'tipo_sala_id' => $this->tipo->id, 'obligatorio' => true]);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['tipo_requerimiento' => 'Sala', 'cantidad' => 2, 'tipo_sala_id' => $this->tipo->id, 'capacidad_minima' => 30,
            'nombre_recurso' => null, 'prioridad_recurso_id' => null, 'obligatorio' => 1, 'estado' => 1, 'observaciones' => 'Revisión académica', 'version_id' => $this->version->id], $cambios);
    }

    private function indice(array $datos = []): string { return route('programacion.requerimientos.index', $datos + ['periodo_id' => $this->version->periodo_academico_id, 'version_id' => $this->version->id]); }
    private function crear(): string { return route('programacion.requerimientos.create', ['actividad' => $this->actividad->id, 'version_id' => $this->version->id]); }
    private function guardar(): string { return route('programacion.requerimientos.store', ['actividad' => $this->actividad->id]); }
    private function editar(Models\RequerimientoActividad $r): string { return route('programacion.requerimientos.edit', ['actividad' => $this->actividad->id, 'requerimiento' => $r->id, 'version_id' => $this->version->id]); }
    private function actualizar(Models\RequerimientoActividad $r): string { return route('programacion.requerimientos.update', ['actividad' => $this->actividad->id, 'requerimiento' => $r->id]); }
}
