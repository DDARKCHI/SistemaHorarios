<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class SolicitudesCambioTest extends IsolatedDatabaseTestCase
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
        foreach (['generar programacion semestral', 'asignar profesores', 'ver programacion semestral', 'asignar horarios'] as $permiso) $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
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

    private function crear(int $alternativas = 1): Models\SolicitudCambioHorario
    {
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->post(route('programacion.cambios.store', $this->horario), ['firma' => $c['firma'], 'motivo' => 'Ajustar horario de la clase', 'fecha_limite' => today()->addDays(7)->toDateString(),
            'opciones' => array_slice(array_keys($c['opciones']), 0, $alternativas)])->assertSessionHasNoErrors()->assertRedirect();
        return Models\SolicitudCambioHorario::latest('id')->firstOrFail();
    }

    private function enlace(Models\RespuestaSolicitudCambio $r): string
    {
        return URL::temporarySignedRoute('programacion.cambios.responder', $r->solicitud->fecha_limite, ['respuesta' => $r->id]);
    }

    private function aceptar(Models\SolicitudCambioHorario $s): void
    {
        foreach ($s->respuestasDocentes as $r) $this->post($this->enlace($r), ['decision' => 'aceptar', 'opcion_id' => $s->opciones()->firstOrFail()->id])->assertSessionHasNoErrors();
    }

    public function test_flujo_completo_no_mueve_horario_hasta_revision_y_conserva_recursos(): void
    {
        $antes = $this->horario->fresh()->getAttributes();
        $this->get(route('programacion.asignacion.horarios', ['periodo_id' => $this->periodo->id, 'seccion_id' => $this->grupo->actividadSeccion->seccion_id]))
            ->assertOk()->assertSee(route('programacion.cambios.create', $this->horario), false)->assertSee('Solicitar cambio');
        $this->get(route('programacion.cambios.create', $this->horario))->assertOk()->assertSee('Alternativas disponibles');
        $s = $this->crear();
        $this->get(route('programacion.cambios.index'))->assertOk()->assertSee('Seguimiento de solicitudes');
        $this->get(route('programacion.cambios.show', $s))->assertOk()->assertSee('Respuestas y enlaces privados');
        $this->aceptar($s);
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertSame('Respondida', $s->fresh()->estado);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $opcion = $s->opciones()->firstOrFail();
        $this->assertDatabaseHas('horarios', ['id' => $this->horario->id, 'dia_id' => $opcion->dia_id, 'bloque_horario_id' => $opcion->bloque_horario_id]);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->horario->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        $this->assertDatabaseCount('asignaciones_salas', 1); $this->assertSame('Aplicada', $s->fresh()->estado);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Aplicar cambio de horario', 'registro_id' => $s->id]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertDatabaseCount('horarios', 1);
    }

    public function test_duplicados_y_cambios_del_origen_se_rechazan(): void
    {
        $s = $this->crear();
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $datos = ['firma' => $c['firma'], 'motivo' => 'Duplicada', 'fecha_limite' => today()->addDay()->toDateString(), 'opciones' => array_slice(array_keys($c['opciones']), 0, 1)];
        $this->post(route('programacion.cambios.store', $this->horario), $datos)->assertSessionHasErrors('cambio');
        $this->aceptar($s); $this->horario->update(['observaciones' => 'Cambió el origen']);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertSame('Respondida', $s->fresh()->estado);
    }

    public function test_rechazo_docente_y_cancelacion_no_mueven_horario(): void
    {
        $s = $this->crear(); $antes = $this->horario->fresh()->getAttributes();
        $r = $s->respuestasDocentes()->firstOrFail();
        $this->post($this->enlace($r), ['decision' => 'rechazar'])->assertSessionHasErrors('observaciones');
        $this->post($this->enlace($r), ['decision' => 'rechazar', 'observaciones' => 'No puedo asistir.'])->assertSessionHasNoErrors();
        $this->assertSame('Rechazada', $s->fresh()->estado);
        $nueva = $this->crear();
        $this->post(route('programacion.cambios.revisar', $nueva), ['decision' => 'cancelar'])->assertSessionHasErrors('motivo');
        $this->post(route('programacion.cambios.revisar', $nueva), ['decision' => 'cancelar', 'motivo' => 'Ya no se necesita.'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $nueva->fresh()->estado);
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
    }

    public function test_opciones_ajenas_firma_ausente_y_repeticion_no_se_admiten(): void
    {
        $s = $this->crear(); $r = $s->respuestasDocentes()->firstOrFail();
        $this->get('/respuesta-cambio/'.$r->id)->assertForbidden();
        $this->post($this->enlace($r), ['decision' => 'aceptar', 'opcion_id' => 999999])->assertSessionHasErrors('cambio');
        $this->aceptar($s);
        $this->post($this->enlace($r), ['decision' => 'aceptar', 'opcion_id' => $s->opciones()->firstOrFail()->id])->assertGone();
        $this->assertSame('Respondida', $s->fresh()->estado);
    }

    public function test_todos_los_docentes_deben_responder_y_coincidir_en_opcion(): void
    {
        $otro = Models\Profesor::create(['universidad_id' => $this->profesor->universidad_id, 'rut' => '33333333', 'nombre' => 'Luis', 'apellido' => 'Soto', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $s = $this->crear(2); $respuestas = $s->respuestasDocentes; $opciones = $s->opciones;
        $this->post($this->enlace($respuestas[0]), ['decision' => 'aceptar', 'opcion_id' => $opciones[0]->id])->assertSessionHasNoErrors();
        $this->assertSame('Pendiente de respuesta', $s->fresh()->estado);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->post($this->enlace($respuestas[1]), ['decision' => 'aceptar', 'opcion_id' => $opciones[1]->id])->assertSessionHasNoErrors();
        $this->assertSame('Respondida', $s->fresh()->estado);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
    }

    public function test_si_otro_ocupa_la_sala_antes_de_aplicar_no_se_mueve_la_clase(): void
    {
        $s = $this->crear(); $this->aceptar($s); $opcion = $s->opciones()->firstOrFail();
        $otroGrupo = $this->grupo->replicate(); $otroGrupo->codigo = 'G:2'; $otroGrupo->numero = 2; $otroGrupo->save();
        $ocupado = $this->horario->replicate(); $ocupado->grupo_seccion_id = $otroGrupo->id; $ocupado->dia_id = $opcion->dia_id; $ocupado->bloque_horario_id = $opcion->bloque_horario_id; $ocupado->save();
        $a = Models\AsignacionSala::firstOrFail()->replicate(); $a->horario_id = $ocupado->id; $a->save();
        $antes = $this->horario->fresh()->getAttributes();
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
    }

    public function test_expiracion_y_publicacion_posterior_invalidan_respuestas_y_aplicacion(): void
    {
        $s = $this->crear(); $r = $s->respuestasDocentes()->firstOrFail(); $url = $this->enlace($r);
        $this->version->update(['estado' => 'Publicado']);
        $this->post($url, ['decision' => 'aceptar', 'opcion_id' => $s->opciones()->firstOrFail()->id])->assertGone();
        $this->version->update(['estado' => 'Borrador']);
        $this->travel(9)->days();
        $this->post($url, ['decision' => 'aceptar', 'opcion_id' => $s->opciones()->firstOrFail()->id])->assertForbidden();
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
    }

    public function test_disponibilidad_declarada_y_almuerzo_se_respetan(): void
    {
        $segundo = Models\BloqueHorario::where('numero', 2)->firstOrFail();
        $this->aprobar(['bloques' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id, $this->horario->dia_id.':'.$segundo->id]]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertCount(1, $c['opciones']);
        $this->assertArrayHasKey($this->horario->dia_id.':'.$segundo->id, $c['opciones']);
        $s = $this->crear(); $this->aceptar($s);
        Models\DisponibilidadProfesor::where('bloque_horario_id', $segundo->id)->update(['tipo_disponibilidad' => 'No disponible']);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
    }

    public function test_los_permisos_impiden_crear_consultar_y_aplicar(): void
    {
        $s = $this->crear(); $this->usuario->revokePermissionTo('asignar horarios');
        $this->get(route('programacion.cambios.index'))->assertRedirect();
        $this->get(route('programacion.cambios.create', $this->horario))->assertRedirect();
        $this->get(route('programacion.cambios.show', $s))->assertRedirect();
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertRedirect();
    }

    public function test_fallo_de_historial_revierte_movimiento_y_revision(): void
    {
        $s = $this->crear(); $this->aceptar($s); $antes = $this->horario->fresh()->getAttributes();
        $this->withoutExceptionHandling();
        Models\SolicitudCambioHorario::updated(function ($s) { if ($s->estado === 'Aplicada') DB::statement('DROP TABLE historial_acciones'); });
        try { $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar']); $this->fail('Se esperaba un fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\SolicitudCambioHorario::flushEventListeners(); }
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertSame('Respondida', $s->fresh()->estado);
        $this->assertDatabaseCount('horarios_profesores', 0);
    }

    public function test_no_elimina_modulos_con_historial_de_solicitudes(): void
    {
        $this->crear();
        $this->delete(route('programacion.asignacion.horarios.eliminar', $this->horario))->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('solicitudes_cambio_horario', 1);
    }

    public function test_rechazo_de_coordinacion_conserva_horario_y_no_requiere_respuesta_previa(): void
    {
        $s = $this->crear(); $antes = $this->horario->fresh()->getAttributes();
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'rechazar', 'motivo' => 'Se conserva el horario.'])->assertSessionHasNoErrors();
        $this->assertSame('Rechazada', $s->fresh()->estado);
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
    }

    public function test_aplica_solo_cuando_todos_los_docentes_aceptan_la_misma_opcion(): void
    {
        $otro = Models\Profesor::create(['universidad_id' => $this->profesor->universidad_id, 'rut' => '55555555', 'nombre' => 'Luis', 'apellido' => 'Soto', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $s = $this->crear(); $this->aceptar($s);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $this->assertSame('Aplicada', $s->fresh()->estado);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->horario->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $this->assertDatabaseCount('horarios_profesores', 2);
    }

    public function test_laboratorios_distintos_en_una_sala_comparten_ocupacion_por_intervalos(): void
    {
        $sala = Models\Sala::firstOrFail();
        $laboratorio = Models\Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'LAB-2', 'nombre' => 'Segundo laboratorio', 'capacidad' => 20, 'estado' => true, 'disponible' => true]);
        $segundaSeccion = $this->grupo->actividadSeccion->seccion->replicate(); $segundaSeccion->codigo = '12'; $segundaSeccion->save();
        $actividad = $this->grupo->actividadSeccion->replicate(); $actividad->seccion_id = $segundaSeccion->id; $actividad->save();
        $grupo = $this->grupo->replicate(); $grupo->actividad_seccion_id = $actividad->id; $grupo->save();
        $segundo = Models\BloqueHorario::where('numero', 2)->firstOrFail();
        $alias = $segundo->replicate(); $alias->numero = 100; $alias->hora_inicio = '09:30:00'; $alias->hora_fin = '10:20:00'; $alias->save();
        $ocupado = $this->horario->replicate(); $ocupado->actividad_seccion_id = $actividad->id; $ocupado->grupo_seccion_id = $grupo->id; $ocupado->bloque_horario_id = $alias->id; $ocupado->save();
        Models\AsignacionLaboratorio::create(['horario_id' => $ocupado->id, 'laboratorio_id' => $laboratorio->id, 'estado' => true]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayNotHasKey($this->horario->dia_id.':'.$segundo->id, $c['opciones']);
        $this->assertNotEmpty($c['opciones']);
    }

    public function test_no_ofrece_cambios_de_duracion_almuerzo_ni_el_mismo_intervalo_con_otro_id(): void
    {
        $largo = Models\BloqueHorario::create(['numero' => 100, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'estado' => true]);
        $almuerzo = Models\BloqueHorario::create(['numero' => 101, 'hora_inicio' => '13:00:00', 'hora_fin' => '13:50:00', 'es_almuerzo' => false, 'estado' => true]);
        $alias = $this->horario->bloqueHorario->replicate(); $alias->numero = 102; $alias->save();
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        foreach ($c['opciones'] as $opcion) {
            $this->assertNotSame($largo->id, $opcion['bloque_horario_id']);
            $this->assertNotSame($almuerzo->id, $opcion['bloque_horario_id']);
        }
        $this->assertArrayNotHasKey($this->horario->dia_id.':'.$alias->id, $c['opciones']);
    }

    public function test_cambio_de_intervalo_de_la_opcion_aceptada_exige_nueva_solicitud(): void
    {
        $s = $this->crear(); $this->aceptar($s); $opcion = $s->opciones()->firstOrFail();
        $opcion->bloqueHorario->update(['hora_inicio' => '07:20:00']);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertSame('Respondida', $s->fresh()->estado);
    }

    public function test_clases_sin_recurso_o_docente_no_se_pueden_proponer(): void
    {
        Models\GrupoProfesor::query()->delete();
        $this->from(route('inicio'))->get(route('programacion.cambios.create', $this->horario))
            ->assertRedirect(route('programacion.cambios.index', [
                'periodo_id' => $this->periodo->id,
                'q' => $this->horario->actividadSeccion->seccion->asignatura->codigo,
            ]))->assertSessionHasErrors('cambio');
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        Models\AsignacionSala::query()->delete();
        $this->get(route('programacion.cambios.create', $this->horario))->assertSessionHasErrors('cambio');
        $this->assertDatabaseCount('solicitudes_cambio_horario', 0);
    }

    public function test_registros_inactivos_del_mismo_grupo_no_producen_claves_duplicadas(): void
    {
        $segundo = Models\BloqueHorario::where('numero', 2)->firstOrFail();
        $inactivo = $this->horario->replicate(); $inactivo->bloque_horario_id = $segundo->id; $inactivo->estado = false; $inactivo->save();
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayNotHasKey($this->horario->dia_id.':'.$segundo->id, $c['opciones']);
        $this->assertNotEmpty($c['opciones']);
    }

    public function test_docente_responde_sin_cuenta_mediante_enlace_privado(): void
    {
        $s = $this->crear(); $r = $s->respuestasDocentes()->firstOrFail(); $url = $this->enlace($r); $opcion = $s->opciones()->firstOrFail();
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Solicitud de cambio de horario')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($url, ['decision' => 'aceptar', 'opcion_id' => $opcion->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Tu respuesta ya fue recibida');
        $this->assertSame('Respondida', $s->fresh()->estado);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Responder solicitud de cambio', 'registro_id' => $s->id, 'usuario_id' => null]);
    }
}
