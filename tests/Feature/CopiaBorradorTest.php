<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class CopiaBorradorTest extends IsolatedDatabaseTestCase
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

    private function solicitud(): array
    {
        $r = $this->get(route('programacion.version.copia', $this->version)); $r->assertOk();
        return ['firma' => $r->viewData('firma'), 'solicitud' => $r->viewData('solicitud'), 'activar' => 1, 'confirmar' => 1];
    }

    public function test_copia_version_publicada_y_permite_editar_sin_cambiar_original(): void
    {
        $this->aprobar();
        foreach (['aprobar', 'publicar'] as $etapa) {
            $r = app(\App\Services\CierreProgramacion::class)->revisar($this->version->fresh());
            $this->post(route('programacion.version.cerrar', $this->version), ['etapa' => $etapa, 'firma' => $r['firma'], 'confirmar' => 1])->assertSessionHasNoErrors();
        }
        $antes = $this->horario->fresh()->getAttributes(); $publicada = Models\CierreProgramacion::where('etapa', 'Publicado')->sole()->getAttributes();
        $datos = $this->solicitud();
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasNoErrors();
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail();
        $this->assertTrue($nueva->es_actual); $this->assertSame('Borrador', $nueva->estado); $this->assertNull($nueva->fecha_publicacion);
        $this->assertFalse($this->version->fresh()->es_actual); $this->assertSame('Publicado', $this->version->fresh()->estado);
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertSame($publicada, Models\CierreProgramacion::where('etapa', 'Publicado')->sole()->getAttributes());
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $nueva->horarios()->sole()->id, 'profesor_id' => $this->profesor->id]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('asignar horarios', 'web'));
        $this->delete(route('programacion.asignacion.horarios.eliminar', $nueva->horarios()->sole()))->assertSessionHasNoErrors();
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
    }

    public function test_copia_sin_activar_y_doble_envio_no_duplica(): void
    {
        $datos = $this->solicitud(); $datos['activar'] = 0;
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasNoErrors();
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('versiones_planificacion', 2);
        $this->assertTrue($this->version->fresh()->es_actual);
        $this->assertFalse(Models\VersionPlanificacion::latest('id')->firstOrFail()->es_actual);
        $this->assertSame(1, DB::table('historial_acciones')->where('accion', 'Crear borrador para ajustes')->count());
    }

    public function test_permite_copiar_base_con_observaciones_para_corregirlas(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 2]);
        $datos = $this->solicitud();
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasNoErrors();
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail();
        $this->assertTrue($nueva->es_actual);
        $this->assertSame('Borrador', $nueva->estado);
        $this->assertGreaterThan(0, app(\App\Services\RevisionConflictosProgramacion::class)->revisar($nueva)['carga_observaciones']);
    }

    public function test_detecta_fuente_cambiada_y_exige_confirmacion(): void
    {
        $datos = $this->solicitud();
        $sin = $datos; unset($sin['confirmar']); $this->post(route('programacion.version.copiar', $this->version), $sin)->assertSessionHasErrors('confirmar');
        $this->horario->update(['observaciones' => 'Edición posterior']);
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasErrors('copia');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_respeta_permiso_sesion_y_vencimiento(): void
    {
        $datos = $this->solicitud(); $sin = $datos; $sin['solicitud'] = (string) \Illuminate\Support\Str::uuid();
        $this->post(route('programacion.version.copiar', $this->version), $sin)->assertGone();
        $this->travel(31)->minutes(); $this->post(route('programacion.version.copiar', $this->version), $datos)->assertGone(); $this->travelBack();
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->get(route('programacion.version.copia', $this->version))->assertRedirect();
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertRedirect();
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_error_historial_revierte_copia_y_activacion(): void
    {
        $datos = $this->solicitud(); $this->withoutExceptionHandling();
        Models\ProfesorVersion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post(route('programacion.version.copiar', $this->version), $datos); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\ProfesorVersion::flushEventListeners(); }
        $this->assertDatabaseCount('versiones_planificacion', 1); $this->assertDatabaseCount('horarios', 1); $this->assertTrue($this->version->fresh()->es_actual);
    }

    public function test_no_copia_si_cambiaron_los_docentes_inferidos(): void
    {
        $datos = $this->solicitud();
        Models\GrupoProfesor::query()->update(['observaciones' => 'Cambio entre revisión y copia']);
        $this->post(route('programacion.version.copiar', $this->version), $datos)->assertSessionHasErrors('copia');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_copia_vinculos_de_laboratorios_y_capacidades_sin_crear_espacios_nuevos(): void
    {
        $sala = Models\Sala::firstOrFail();
        $laboratorio = Models\Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'LAB-1', 'nombre' => 'Computación', 'capacidad' => 20, 'estado' => true, 'disponible' => true]);
        $movil = Models\LaboratorioMovil::create(['universidad_id' => $sala->universidad_id, 'sede_id' => $sala->sede_id, 'codigo' => 'MOV-1', 'nombre' => 'Notebooks', 'capacidad' => 20, 'estado' => true, 'disponible' => true]);
        Models\AsignacionLaboratorio::create(['horario_id' => $this->horario->id, 'laboratorio_id' => $laboratorio->id, 'capacidad_asignada' => 15, 'estado' => true]);
        Models\AsignacionLaboratorioMovil::create(['horario_id' => $this->horario->id, 'laboratorio_movil_id' => $movil->id, 'capacidad_asignada' => 10, 'estado' => true]);
        $this->post(route('programacion.version.copiar', $this->version), $this->solicitud())->assertSessionHasNoErrors();
        $h = Models\VersionPlanificacion::latest('id')->firstOrFail()->horarios()->sole();
        $this->assertDatabaseHas('asignaciones_laboratorios', ['horario_id' => $h->id, 'laboratorio_id' => $laboratorio->id, 'capacidad_asignada' => 15]);
        $this->assertDatabaseHas('asignaciones_laboratorios_moviles', ['horario_id' => $h->id, 'laboratorio_movil_id' => $movil->id, 'capacidad_asignada' => 10]);
        $this->assertDatabaseCount('salas', 1); $this->assertDatabaseCount('laboratorios', 1); $this->assertDatabaseCount('laboratorios_moviles', 1);
    }

    public function test_periodo_inactivo_y_fuente_vacia_no_generan_copias(): void
    {
        $this->periodo->update(['estado' => false]);
        $this->post(route('programacion.version.copiar', $this->version), $this->solicitud())->assertSessionHasErrors('copia');
        $this->periodo->update(['estado' => true]); $this->horario->delete();
        $this->post(route('programacion.version.copiar', $this->version), $this->solicitud())->assertSessionHasErrors('copia');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }
}
