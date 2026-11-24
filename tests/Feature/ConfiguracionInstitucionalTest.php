<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ConfiguracionInstitucionalTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\Universidad $institucion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institucion = Models\Universidad::firstOrFail();
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'config@example.test', 'password' => 'prueba']);
        foreach (['ver configuracion', 'administrar usuarios'] as $p) $this->usuario->givePermissionTo(Permission::findOrCreate($p, 'web'));
        $this->actingAs($this->usuario);
    }
    private function ruta(string $accion, string $tipo, ?int $id = null): string
    {
        return route('configuracion.calendario.'.$accion, array_filter(['tipo' => $tipo, 'id' => $id]));
    }
    private function bloque(array $cambios = []): array
    {
        return $cambios + ['numero' => 16, 'hora_inicio' => '23:10', 'hora_fin' => '23:50', 'estado' => 1, 'es_almuerzo' => 0];
    }
    private function contexto(): array
    {
        $p = Models\PeriodoAcademico::create(['universidad_id' => $this->institucion->id, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $profesor = Models\Profesor::create(['universidad_id' => $this->institucion->id, 'rut' => '12345678', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'estado' => true]);
        return [$p, $profesor];
    }
    private function disponibilidad(Models\Dia $dia, Models\BloqueHorario $bloque): Models\DisponibilidadProfesor
    {
        [$p, $profesor] = $this->contexto();
        return Models\DisponibilidadProfesor::create(['periodo_academico_id' => $p->id, 'profesor_id' => $profesor->id, 'dia_id' => $dia->id,
            'bloque_horario_id' => $bloque->id, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);
    }

    public function test_consulta_datos_y_calendario_con_identidad_prosec(): void
    {
        $this->get(route('configuracion.index'))->assertOk()->assertSee($this->institucion->nombre)->assertSee('Guardar datos institucionales')->assertDontSee('se encuentra en desarrollo');
        $this->get(route('configuracion.calendario'))->assertOk()->assertSee('Módulos horarios')->assertSee('Días de la semana');
        foreach (['dias', 'bloques'] as $tipo) $this->get($this->ruta('create', $tipo))->assertOk()->assertSee('Agregar al calendario');
    }

    public function test_actualiza_institucion_y_perfil_sin_cambiar_identidad_visual_ni_relaciones(): void
    {
        $s = Models\Sede::create(['universidad_id' => $this->institucion->id, 'nombre' => 'Talcahuano', 'estado' => true]);
        $url = route('configuracion.institucion.update', $this->institucion->id);
        $this->put($url, ['nombre' => '  Universidad   de prueba ', 'sigla' => ' UPR ', 'color_principal' => '#000000', 'nombre_sistema' => 'Otro'])->assertSessionHasNoErrors();
        $this->assertSame('Universidad de prueba', $this->institucion->fresh()->nombre);
        $config = $this->institucion->configuracionInstitucion()->firstOrFail();
        $this->assertSame('PROSEC', $config->nombre_sistema); $this->assertSame('#D12421', $config->color_principal); $this->assertSame('UPR', $config->sigla);
        $this->assertSame($this->institucion->id, $s->fresh()->universidad_id);
        $this->put($url, ['nombre' => 'Universidad de prueba', 'sigla' => 'UPR'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 1); $this->assertDatabaseCount('configuracion_institucion', 1);
    }

    public function test_alta_institucion_evita_duplicados_y_no_cambia_la_original(): void
    {
        $this->get(route('configuracion.index', ['nueva' => 1]))->assertOk()->assertSee('Crear institución');
        $this->post(route('configuracion.institucion.store'), ['nombre' => 'Otra institución', 'sigla' => 'OTR'])->assertSessionHasNoErrors();
        $this->post(route('configuracion.institucion.store'), ['nombre' => ' otra institución ', 'sigla' => 'NUE'])->assertSessionHasErrors('nombre');
        $this->post(route('configuracion.institucion.store'), ['nombre' => 'Nombre diferente', 'sigla' => 'otr'])->assertSessionHasErrors('sigla');
        $this->assertDatabaseCount('universidades', 2); $this->assertSame('UCSC', $this->institucion->fresh()->sigla);
    }

    public function test_perfil_lectura_no_modifica_configuracion_ni_calendario(): void
    {
        $this->usuario->revokePermissionTo('administrar usuarios');
        $this->get(route('configuracion.index'))->assertOk()->assertDontSee('Guardar datos institucionales')->assertSee('La edición requiere permisos de administración.');
        $this->get(route('configuracion.calendario'))->assertOk()->assertDontSee('Agregar módulo');
        $this->get(route('configuracion.index', ['nueva' => 1]))->assertForbidden();
        $this->put(route('configuracion.institucion.update', $this->institucion->id), ['nombre' => 'Alterado', 'sigla' => 'A'])->assertRedirect();
        $this->post(route('configuracion.institucion.store'), ['nombre' => 'Otra', 'sigla' => 'O'])->assertRedirect();
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertRedirect();
        $this->put($this->ruta('update', 'dias', Models\Dia::value('id')), ['numero' => 1, 'estado' => 0])->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_sin_permiso_configuracion_ni_sesion_no_consulta(): void
    {
        $this->usuario->revokePermissionTo('ver configuracion');
        $this->get(route('configuracion.index'))->assertRedirect(); $this->get(route('configuracion.calendario'))->assertRedirect();
        $this->post(route('configuracion.institucion.store'), ['nombre' => 'Otra', 'sigla' => 'O'])->assertRedirect();
        auth()->logout(); $this->get(route('configuracion.index'))->assertRedirect(route('login'));
    }

    public function test_crea_modulo_y_corrige_definicion_si_no_tiene_referencias(): void
    {
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertSessionHasNoErrors();
        $b = Models\BloqueHorario::where('numero', 16)->firstOrFail();
        $this->get($this->ruta('edit', 'bloques', $b->id))->assertOk()->assertSee('23:10');
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['hora_fin' => '23:55']))->assertSessionHasNoErrors();
        $this->assertSame('23:55:00', $b->fresh()->hora_fin); $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_numero_duplicado_y_modulos_superpuestos_no_se_guardan(): void
    {
        $b = Models\BloqueHorario::orderBy('hora_inicio')->firstOrFail();
        $this->post($this->ruta('store', 'bloques'), $this->bloque(['numero' => $b->numero]))->assertSessionHasErrors('numero');
        $this->post($this->ruta('store', 'bloques'), $this->bloque(['hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5)]))->assertSessionHasErrors('hora_inicio');
        $this->post($this->ruta('store', 'bloques'), $this->bloque(['hora_inicio' => '23:50', 'hora_fin' => '23:10']))->assertSessionHasErrors('hora_fin');
        $this->assertDatabaseMissing('bloques_horarios', ['numero' => 16]);
    }

    public function test_intervalos_adyacentes_son_validos_y_no_acepta_nocturnos_invertidos(): void
    {
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertSessionHasNoErrors();
        $this->post($this->ruta('store', 'bloques'), $this->bloque(['numero' => 17, 'hora_inicio' => '23:50', 'hora_fin' => '23:59']))->assertSessionHasNoErrors();
        $this->post($this->ruta('store', 'bloques'), $this->bloque(['numero' => 18, 'hora_inicio' => '23:59', 'hora_fin' => '00:30']))->assertSessionHasErrors('hora_fin');
    }

    public function test_dia_nuevo_recibe_nombre_y_abreviatura_coherentes_y_no_repite_numero(): void
    {
        $this->post($this->ruta('store', 'dias'), ['numero' => 7, 'estado' => 1, 'nombre' => 'Otro'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dias', ['numero' => 7, 'nombre' => 'Domingo', 'abreviatura' => 'DO']);
        $this->post($this->ruta('store', 'dias'), ['numero' => 7, 'estado' => 1])->assertSessionHasErrors('numero');
        $this->post($this->ruta('store', 'dias'), ['numero' => 8, 'estado' => 1])->assertSessionHasErrors('numero');
    }

    public function test_disponibilidad_vigente_protege_definicion_y_estado(): void
    {
        $d = Models\Dia::firstOrFail(); $b = Models\BloqueHorario::firstOrFail(); $this->disponibilidad($d, $b);
        $this->put($this->ruta('update', 'dias', $d->id), ['numero' => 7, 'estado' => 1])->assertSessionHasErrors('numero');
        $this->put($this->ruta('update', 'dias', $d->id), ['numero' => $d->numero, 'estado' => 0])->assertSessionHasErrors('estado');
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['numero' => $b->numero, 'hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5), 'es_almuerzo' => 1]))->assertSessionHasErrors('numero');
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['numero' => $b->numero, 'hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5), 'estado' => 0]))->assertSessionHasErrors('estado');
        $this->assertTrue((bool) $b->fresh()->estado);
    }

    public function test_horario_actual_protege_dia_y_modulo_sin_disponibilidad(): void
    {
        [$p] = $this->contexto();
        $r = Models\Asignatura::create(['universidad_id' => $this->institucion->id, 'nombre' => 'Backend', 'codigo' => 'BACK']);
        $s = Models\Seccion::create(['asignatura_id' => $r->id, 'periodo_academico_id' => $p->id, 'codigo' => '11', 'estado_activo' => true]);
        $a = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'estado' => true]);
        $v = Models\VersionPlanificacion::create(['periodo_academico_id' => $p->id, 'nombre' => 'Semestral', 'version' => 0.1, 'tipo' => 'semestral', 'estado' => 'Borrador', 'es_actual' => true, 'usuario_id' => $this->usuario->id]);
        $d = Models\Dia::firstOrFail(); $b = Models\BloqueHorario::firstOrFail();
        Models\Horario::create(['actividad_seccion_id' => $a->id, 'version_planificacion_id' => $v->id, 'dia_id' => $d->id, 'bloque_horario_id' => $b->id, 'estado' => true]);
        $this->put($this->ruta('update', 'dias', $d->id), ['numero' => $d->numero, 'estado' => 0])->assertSessionHasErrors('estado');
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['numero' => $b->numero, 'hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5), 'estado' => 0]))->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('horarios', 1);
    }

    public function test_referencia_historica_admite_desactivar_sin_cambiar_intervalo(): void
    {
        $d = Models\Dia::firstOrFail(); $b = Models\BloqueHorario::firstOrFail(); $disp = $this->disponibilidad($d, $b);
        $disp->periodoAcademico->update(['estado' => false]);
        $this->put($this->ruta('update', 'dias', $d->id), ['numero' => $d->numero, 'estado' => 0])->assertSessionHasNoErrors();
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['numero' => $b->numero, 'hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5), 'estado' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('disponibilidad_profesores', 1);
    }

    public function test_reactivar_modulo_inactivo_comprueba_superposicion(): void
    {
        $b = Models\BloqueHorario::firstOrFail();
        $otro = Models\BloqueHorario::create(['numero' => 16, 'hora_inicio' => $b->hora_inicio, 'hora_fin' => $b->hora_fin, 'estado' => false]);
        $this->put($this->ruta('update', 'bloques', $otro->id), $this->bloque(['hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5)]))->assertSessionHasErrors('hora_inicio');
        $this->assertFalse((bool) $otro->fresh()->estado);
    }

    public function test_no_desactiva_ultimo_dia_o_modulo_activo(): void
    {
        $d = Models\Dia::firstOrFail(); Models\Dia::whereKeyNot($d->id)->update(['estado' => false]);
        $this->put($this->ruta('update', 'dias', $d->id), ['numero' => $d->numero, 'estado' => 0])->assertSessionHasErrors('estado');
        $b = Models\BloqueHorario::firstOrFail(); Models\BloqueHorario::whereKeyNot($b->id)->update(['estado' => false]);
        $this->put($this->ruta('update', 'bloques', $b->id), $this->bloque(['numero' => $b->numero, 'hora_inicio' => substr($b->hora_inicio, 0, 5), 'hora_fin' => substr($b->hora_fin, 0, 5), 'estado' => 0]))->assertSessionHasErrors('estado');
    }

    public function test_campos_malformados_recuperan_formularios_sin_error(): void
    {
        $url = route('configuracion.index');
        $this->from($url)->put(route('configuracion.institucion.update', $this->institucion->id), ['nombre' => ['inválido'], 'sigla' => [1]])->assertSessionHasErrors(['nombre', 'sigla']);
        $this->get($url)->assertOk();
        foreach (['dias', 'bloques'] as $tipo) {
            $url = $this->ruta('create', $tipo);
            $this->from($url)->post($this->ruta('store', $tipo), ['numero' => [1], 'estado' => [1]])->assertSessionHasErrors(['numero', 'estado']);
            $this->get($url)->assertOk();
        }
    }

    public function test_calendario_no_cambia_mientras_docentes_responden_o_coordinacion_revisa(): void
    {
        [$p, $profesor] = $this->contexto();
        $s = Models\SolicitudDisponibilidad::create(['periodo_academico_id' => $p->id, 'profesor_id' => $profesor->id, 'estado' => 'Pendiente', 'fecha_limite' => today()]);
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertSessionHasErrors('calendario');
        $s->update(['estado' => 'En revisión', 'fecha_limite' => today()->subDay()]);
        $this->post($this->ruta('store', 'dias'), ['numero' => 7, 'estado' => 1])->assertSessionHasErrors('calendario');
        $this->assertDatabaseMissing('dias', ['numero' => 7]);
        $s->update(['estado' => 'Rechazada']);
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertSessionHasNoErrors();
    }

    public function test_solicitud_vencida_no_impide_calendario_y_fallo_auditoria_revierte_modulo(): void
    {
        [$p, $profesor] = $this->contexto();
        Models\SolicitudDisponibilidad::create(['periodo_academico_id' => $p->id, 'profesor_id' => $profesor->id, 'estado' => 'Pendiente', 'fecha_limite' => today()->subDay()]);
        $this->post($this->ruta('store', 'bloques'), $this->bloque())->assertSessionHasNoErrors();
        $this->withoutExceptionHandling();
        Models\BloqueHorario::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->ruta('store', 'bloques'), $this->bloque(['numero' => 17, 'hora_inicio' => '23:50', 'hora_fin' => '23:59'])); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\BloqueHorario::flushEventListeners(); }
        $this->assertDatabaseMissing('bloques_horarios', ['numero' => 17]);
    }

    public function test_fallo_auditoria_revierte_institucion_y_su_perfil(): void
    {
        $nombre = $this->institucion->nombre; $this->withoutExceptionHandling();
        Models\ConfiguracionInstitucion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->put(route('configuracion.institucion.update', $this->institucion->id), ['nombre' => 'Modificado', 'sigla' => 'MOD']); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\ConfiguracionInstitucion::flushEventListeners(); }
        $this->assertSame($nombre, $this->institucion->fresh()->nombre); $this->assertDatabaseCount('configuracion_institucion', 0);
    }
}
