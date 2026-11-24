<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ActivacionVersionTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Profesor $profesor;
    private Models\GrupoSeccion $grupo;
    private Models\Horario $horario;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Planificación', 'rut' => '17777777', 'email' => 'preparacion@example.test', 'password' => 'prueba']);
        foreach (['generar programacion semestral', 'asignar profesores', 'ver programacion semestral'] as $permiso) $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'BACK-1', 'nombre' => 'Backend', 'estado' => true]);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '11', 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'cantidad_estudiantes' => 20, 'estado' => true]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'numero' => 1, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'rut' => '12345678', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $this->horario = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $this->grupo->id, 'version_planificacion_id' => $this->version->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id'), 'estado' => true]);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Talcahuano', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Cátedra', 'estado' => true, 'requiere_equipamiento' => false]);
        $sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'TH-01', 'nombre' => 'TH-01', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        Models\AsignacionSala::create(['horario_id' => $this->horario->id, 'sala_id' => $sala->id, 'estado' => true, 'es_principal' => true]);
    }

    private function aprobar(array $datos = []): Models\SolicitudDisponibilidad
    {
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $solicitud = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $url = URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), ['solicitud' => $solicitud->id]);
        $this->post($url, $datos ?: ['bloques' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id]])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $solicitud), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        return $solicitud;
    }

    private function borrador(): Models\VersionPlanificacion
    {
        $v = $this->version->replicate(); $v->version = '1.1'; $v->es_actual = false; $v->save();
        $h = $this->horario->replicate(); $h->version_planificacion_id = $v->id; $h->save();
        $a = Models\AsignacionSala::firstOrFail()->replicate(); $a->horario_id = $h->id; $a->save();
        return $v;
    }

    private function firma(Models\VersionPlanificacion $version): string
    {
        return app(\App\Services\ActivacionVersion::class)->revisar($version->fresh())['firma'];
    }

    public function test_activa_borrador_completo_y_conserva_todos_los_horarios_y_estados(): void
    {
        $this->aprobar(); $v = $this->borrador();
        $antes = Models\Horario::orderBy('id')->get()->toArray();
        $this->get(route('programacion.version.revisar', $v))->assertOk()->assertSee('El borrador supera')->assertSee('Activar esta versión');
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($v->fresh()->es_actual); $this->assertFalse($this->version->fresh()->es_actual);
        $this->assertSame('Borrador', $v->fresh()->estado);
        $this->assertSame($antes, Models\Horario::orderBy('id')->get()->toArray());
        $this->assertSame(1, Models\VersionPlanificacion::where('es_actual', true)->where('tipo', 'semestral')->count());
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Activar versión semestral', 'registro_id' => $v->id]);
    }

    public function test_no_activa_sin_confirmacion_ni_con_firma_antigua(): void
    {
        $this->aprobar(); $v = $this->borrador(); $firma = $this->firma($v);
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma])->assertSessionHasErrors('confirmar');
        $v->horarios()->update(['observaciones' => 'Cambio desde otra pestaña']);
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertTrue($this->version->fresh()->es_actual); $this->assertFalse($v->fresh()->es_actual);
    }

    public function test_firma_cambia_si_otro_borrador_se_activa_y_permite_volver_a_version_anterior(): void
    {
        $this->aprobar(); $v = $this->borrador(); $firma = $this->firma($v);
        $otro = $this->borrador(); $otro->update(['version' => '1.2']);
        $this->post(route('programacion.version.activar', $otro), ['firma' => $this->firma($otro), 'confirmar' => 1])->assertSessionHasNoErrors();
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->post(route('programacion.version.activar', $this->version), ['firma' => $this->firma($this->version), 'confirmar' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($this->version->fresh()->es_actual); $this->assertFalse($otro->fresh()->es_actual);
    }

    public function test_bloquea_carga_incompleta_falta_sala_y_disponibilidad(): void
    {
        $v = $this->borrador(); $v->horarios()->sole()->asignacionesSalas()->delete();
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 2]);
        $this->get(route('programacion.version.revisar', $v))->assertOk()->assertSee('asignaciones incompletas')->assertSee('observaciones de carga')->assertSee('disponibilidades por revisar');
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertTrue($this->version->fresh()->es_actual);
    }

    public function test_no_activa_periodo_inactivo_version_cerrada_o_anterior(): void
    {
        $this->aprobar(); $v = $this->borrador();
        $this->periodo->update(['estado' => false]);
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->periodo->update(['estado' => true]); $v->update(['estado' => 'Publicado']);
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
        $v->update(['estado' => 'Borrador', 'tipo' => 'anterior']);
        $this->get(route('programacion.version.revisar', $v))->assertNotFound();
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
    }

    public function test_no_afecta_versiones_actuales_de_otros_periodos_y_no_repite_auditoria(): void
    {
        $this->aprobar(); $v = $this->borrador();
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => '2027-I', 'anio' => 2027, 'numero_semestre' => 1]);
        $otro = $this->version->replicate(); $otro->periodo_academico_id = $periodo->id; $otro->save();
        $firma = $this->firma($v);
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1])->assertSessionHasNoErrors();
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertTrue($otro->fresh()->es_actual);
        $this->assertSame(1, DB::table('historial_acciones')->where('accion', 'Activar versión semestral')->count());
    }

    public function test_sin_permiso_no_consulta_ni_activa_y_el_listado_enlaza_el_borrador(): void
    {
        $v = $this->borrador();
        $this->get(route('programacion.semestral', ['periodo_id' => $this->periodo->id, 'version_id' => $v->id]))->assertOk()->assertSee('Revisar para activar');
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->get(route('programacion.version.revisar', $v))->assertRedirect();
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertRedirect();
        $this->assertFalse($v->fresh()->es_actual);
    }

    public function test_error_de_auditoria_revierte_el_cambio_de_version_actual(): void
    {
        $this->aprobar(); $v = $this->borrador(); $firma = $this->firma($v);
        $this->withoutExceptionHandling();
        Models\VersionPlanificacion::updated(function ($modelo) use ($v) { if ($modelo->id === $v->id) DB::statement('DROP TABLE historial_acciones'); });
        try {
            $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1]);
            $this->fail('Se esperaba un error de auditoría.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('historial_acciones', $e->getMessage());
        } finally { Models\VersionPlanificacion::flushEventListeners(); }
        $this->assertTrue($this->version->fresh()->es_actual); $this->assertFalse($v->fresh()->es_actual);
    }

    public function test_conflictos_capacidad_y_recursos_incompatibles_bloquean_activacion(): void
    {
        $this->aprobar(); $v = $this->borrador();
        $otroGrupo = $this->grupo->replicate(); $otroGrupo->codigo = 'G:2'; $otroGrupo->numero = 2; $otroGrupo->save();
        Models\GrupoProfesor::create(['grupo_seccion_id' => $otroGrupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        $h = $v->horarios()->sole()->replicate(); $h->grupo_seccion_id = $otroGrupo->id; $h->save();
        $a = Models\AsignacionSala::firstOrFail()->replicate(); $a->horario_id = $h->id; $a->save();
        Models\Sala::query()->update(['capacidad' => 1, 'disponible' => false]);
        $this->get(route('programacion.version.revisar', $v))->assertOk()->assertSee('topes horarios')->assertSee('capacidades por revisar')->assertSee('recursos incompatibles');
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertTrue($this->version->fresh()->es_actual);
    }

    public function test_cambio_disponibilidad_entre_revision_y_activacion_impide_guardar(): void
    {
        $this->aprobar(); $v = $this->borrador(); $firma = $this->firma($v);
        Models\DisponibilidadProfesor::where('profesor_id', $this->profesor->id)->update(['tipo_disponibilidad' => 'No disponible']);
        $this->post(route('programacion.version.activar', $v), ['firma' => $firma, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertFalse($v->fresh()->es_actual);
    }

    public function test_sin_sesion_y_borrador_vacio_no_se_pueden_activar(): void
    {
        $v = $this->borrador(); $v->horarios()->delete();
        $this->get(route('programacion.version.revisar', $v))->assertOk()->assertSee('La versión no contiene módulos activos.');
        $this->post(route('programacion.version.activar', $v), ['firma' => $this->firma($v), 'confirmar' => 1])->assertSessionHasErrors('version');
        auth()->logout();
        $this->get(route('programacion.version.revisar', $v))->assertRedirect(route('login'));
        $this->post(route('programacion.version.activar', $v), ['firma' => 'invalida'])->assertRedirect(route('login'));
    }
}
