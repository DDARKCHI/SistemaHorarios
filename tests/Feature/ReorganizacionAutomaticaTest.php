<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ReorganizacionAutomaticaTest extends IsolatedDatabaseTestCase
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

    private function generar(): array
    {
        $r = $this->post(route('programacion.reorganizacion.generar'), ['version_id' => $this->version->id]);
        $r->assertSessionHasNoErrors()->assertRedirect();
        $token = basename(parse_url($r->headers->get('Location'), PHP_URL_PATH));
        return [$token, session('reorganizaciones.'.$token)];
    }

    public function test_vista_previa_y_guardado_no_cambian_base_ni_version_actual(): void
    {
        $this->aprobar(); $antes = $this->horario->fresh()->getAttributes();
        [$token] = $this->generar();
        $this->get(route('programacion.reorganizacion.show', $token))->assertOk()->assertSee('Backend')->assertSee('Guardar como borrador nuevo');
        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasNoErrors()->assertRedirect();
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail();
        $this->assertNotSame($this->version->id, $nueva->id);
        $this->assertFalse($nueva->es_actual); $this->assertTrue($this->version->fresh()->es_actual);
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $nueva->horarios()->sole()->id, 'profesor_id' => $this->profesor->id]);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Reorganizar programación', 'registro_id' => $nueva->id]);
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertGone();
        $this->assertDatabaseCount('versiones_planificacion', 2);
    }

    public function test_resuelve_topes_globales_de_docentes_secciones_y_salas(): void
    {
        $segundo = DB::table('bloques_horarios')->where('numero', 2)->value('id');
        $otraSeccion = $this->grupo->actividadSeccion->seccion->replicate(); $otraSeccion->codigo = '12'; $otraSeccion->save();
        $actividad = $this->grupo->actividadSeccion->replicate(); $actividad->seccion_id = $otraSeccion->id; $actividad->save();
        $grupo = $this->grupo->replicate(); $grupo->actividad_seccion_id = $actividad->id; $grupo->save();
        Models\GrupoProfesor::create(['grupo_seccion_id' => $grupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        $h = $this->horario->replicate(); $h->grupo_seccion_id = $grupo->id; $h->actividad_seccion_id = $actividad->id; $h->save();
        $a = Models\AsignacionSala::firstOrFail()->replicate(); $a->horario_id = $h->id; $a->save();
        $this->aprobar(['bloques' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id, $this->horario->dia_id.':'.$segundo]]);
        [$token] = $this->generar();
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasNoErrors();
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail();
        $this->assertCount(2, $nueva->horarios); $this->assertCount(2, $nueva->horarios->pluck('bloque_horario_id')->unique());
        $this->assertSame(0, app(\App\Services\RevisionConflictosProgramacion::class)->revisar($nueva)['conflictos']);
    }

    public function test_bloqueos_no_generan_propuestas_ni_versiones(): void
    {
        $this->post(route('programacion.reorganizacion.generar'), ['version_id' => $this->version->id])->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertEmpty(session('reorganizaciones', []));
    }

    public function test_cambios_posteriores_invalidan_guardado_sin_modificar_base(): void
    {
        $this->aprobar(); [$token] = $this->generar();
        $this->horario->update(['observaciones' => 'Cambio posterior a la propuesta']);
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->get(route('programacion.reorganizacion.show', $token))->assertRedirect()->assertSessionHasErrors('generacion');
    }

    public function test_respeta_vencimiento_y_sesion_de_la_propuesta(): void
    {
        $this->aprobar(); [$token] = $this->generar();
        $p = session('reorganizaciones.'.$token); $p['usuario_id'] = 999999;
        session()->put('reorganizaciones.'.$token, $p);
        $this->get(route('programacion.reorganizacion.show', $token))->assertGone();
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertGone();
        $p['usuario_id'] = $this->usuario->id; session()->put('reorganizaciones.'.$token, $p);
        $this->travel(31)->minutes();
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertGone();
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_guardado_rechaza_plan_alterado_y_permisos_faltantes(): void
    {
        $this->aprobar(); [$token, $p] = $this->generar();
        $p['plan'][$this->horario->id] = '999:999'; session()->put('reorganizaciones.'.$token, $p);
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->post(route('programacion.reorganizacion.generar'), ['version_id' => $this->version->id])->assertRedirect();
        $this->get(route('programacion.reorganizacion.show', $token))->assertRedirect();
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertRedirect();
    }

    public function test_fallo_del_historial_revierte_toda_la_version_nueva(): void
    {
        $this->aprobar(); [$token] = $this->generar();
        $this->withoutExceptionHandling();
        Models\ProfesorVersion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try {
            $this->post(route('programacion.reorganizacion.guardar', $token));
            $this->fail('Se esperaba un error de auditoría.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('historial_acciones', $e->getMessage());
        } finally { Models\ProfesorVersion::flushEventListeners(); }
        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertDatabaseCount('horarios', 1);
        $this->assertTrue($this->version->fresh()->es_actual);
    }

    public function test_no_elige_modulos_de_otra_duracion_ni_almuerzo(): void
    {
        $largo = Models\BloqueHorario::create(['numero' => 100, 'hora_inicio' => '08:10:00', 'hora_fin' => '09:40:00', 'es_almuerzo' => false, 'estado' => true]);
        $claves = [];
        foreach (Models\Dia::where('estado', true)->get() as $dia) foreach (Models\BloqueHorario::where('estado', true)->get() as $bloque) $claves[] = $dia->id.':'.$bloque->id;
        $this->aprobar(['bloques' => $claves]);
        $datos = app(\App\Services\ReorganizacionAutomatica::class)->preparar($this->version->fresh());
        foreach ($datos['trabajos'][0]['opciones'] as $id) {
            $o = $datos['opciones'][$id];
            $this->assertSame(50 * 60, $o['fin'] - $o['inicio']);
            $this->assertNotSame($largo->id, $o['bloque_id']);
            $this->assertFalse($o['inicio'] < 14 * 3600 && 13 * 3600 < $o['fin']);
        }
    }

    public function test_rechaza_docentes_de_la_misma_clase_sin_horarios_comunes(): void
    {
        $this->aprobar();
        $otro = Models\Profesor::create(['universidad_id' => $this->profesor->universidad_id, 'nombre' => 'Luis', 'apellido' => 'Soto', 'rut' => '22222222', 'estado' => true]);
        Models\HorarioProfesor::create(['horario_id' => $this->horario->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        Models\HorarioProfesor::create(['horario_id' => $this->horario->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $this->profesor = $otro;
        $martes = Models\Dia::where('numero', 2)->value('id');
        $this->aprobar(['bloques' => [$martes.':'.$this->horario->bloque_horario_id]]);
        $this->post(route('programacion.reorganizacion.generar'), ['version_id' => $this->version->id])->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_cambio_de_capacidad_invalida_vista_previa_aunque_siga_siendo_suficiente(): void
    {
        $this->aprobar(); [$token] = $this->generar();
        Models\Sala::query()->update(['capacidad' => 40]);
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_numeracion_continua_despues_de_diez_y_la_copia_anterior_sigue_funcionando(): void
    {
        foreach (['9.9', '10.0'] as $numero) {
            $v = $this->version->replicate(); $v->version = $numero; $v->es_actual = false; $v->save();
        }
        $this->aprobar(); [$token] = $this->generar();
        $this->post(route('programacion.reorganizacion.guardar', $token))->assertSessionHasNoErrors();
        $this->assertSame('10.1', Models\VersionPlanificacion::latest('id')->firstOrFail()->version);
        $fuente = $this->version->replicate(); $fuente->tipo = 'anterior'; $fuente->es_actual = false; $fuente->save();
        $horario = $this->horario->replicate(); $horario->version_planificacion_id = $fuente->id; $horario->save();
        $copia = app(\App\Services\ProgramacionSemestralService::class)->generar($fuente->id, $this->usuario->id);
        $this->assertSame('10.2', $copia->version);
        $this->assertTrue($copia->es_actual);
        $this->assertCount(1, $copia->horarios);
    }
}
