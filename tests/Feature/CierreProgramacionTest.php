<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class CierreProgramacionTest extends IsolatedDatabaseTestCase
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

    private function cerrar(string $etapa)
    {
        $r = app(\App\Services\CierreProgramacion::class)->revisar($this->version->fresh());
        return $this->post(route('programacion.version.cerrar', $this->version), ['etapa' => $etapa, 'firma' => $r['firma'], 'confirmar' => 1]);
    }

    public function test_aprueba_y_publica_con_historial_y_docentes_fijos(): void
    {
        $this->aprobar();
        $this->get(route('programacion.version.cierre', $this->version))->assertOk()->assertSee('Aprobar horario');
        $this->cerrar('aprobar')->assertSessionHasNoErrors();
        $this->assertSame('Aprobado', $this->version->fresh()->estado);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->horario->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        $this->get(route('programacion.version.cierre', $this->version))->assertOk()->assertSee('Publicar horario');
        $this->cerrar('publicar')->assertSessionHasNoErrors();
        $this->assertSame('Publicado', $this->version->fresh()->estado);
        $this->assertNotNull($this->version->fresh()->fecha_publicacion);
        $this->assertDatabaseCount('cierres_programacion', 2);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Publicar programación', 'registro_id' => $this->version->id]);
        $this->get(route('programacion.version.publicada', $this->version))->assertOk()->assertSee('Backend')->assertSee('Pérez')->assertSee('TH-01');
    }

    public function test_publicacion_conserva_nombres_anteriores_a_cambios_de_catalogo(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors(); $this->cerrar('publicar')->assertSessionHasNoErrors();
        $this->profesor->update(['apellido' => 'Nombre cambiado']);
        Models\Sala::query()->update(['codigo' => 'NUEVO']);
        $this->get(route('programacion.version.publicada', $this->version))->assertOk()->assertSee('Pérez')->assertSee('TH-01')->assertDontSee('Nombre cambiado')->assertDontSee('NUEVO');
        $this->get(route('programacion.version.publicada', ['version' => $this->version, 'q' => 'TH-01']))->assertOk()->assertSee('Backend');
        $this->get(route('programacion.version.publicada', ['version' => $this->version, 'q' => 'inexistente']))->assertOk()->assertSee('No hay clases');
    }

    public function test_no_publica_sin_aprobacion_ni_sin_confirmacion(): void
    {
        $this->aprobar();
        $this->cerrar('publicar')->assertSessionHasErrors('cierre');
        $firma = app(\App\Services\CierreProgramacion::class)->revisar($this->version->fresh())['firma'];
        $this->post(route('programacion.version.cerrar', $this->version), ['etapa' => 'aprobar', 'firma' => $firma])->assertSessionHasErrors('confirmar');
        $this->assertSame('Borrador', $this->version->fresh()->estado);
        $this->assertDatabaseCount('cierres_programacion', 0);
    }

    public function test_no_aprueba_version_con_observaciones_o_no_actual(): void
    {
        $this->cerrar('aprobar')->assertSessionHasErrors('cierre');
        $this->aprobar(); $this->version->update(['es_actual' => false]);
        $this->cerrar('aprobar')->assertSessionHasErrors('cierre');
        $this->assertDatabaseCount('cierres_programacion', 0);
    }

    public function test_cambios_posteriores_a_aprobacion_impiden_publicacion(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors();
        $this->horario->update(['observaciones' => 'Cambio después de aprobar']);
        $this->cerrar('publicar')->assertSessionHasErrors('cierre');
        $this->assertSame('Aprobado', $this->version->fresh()->estado);
        $this->assertDatabaseCount('cierres_programacion', 1);
    }

    public function test_revision_de_otra_pestana_y_doble_envio_no_duplican_cierres(): void
    {
        $this->aprobar(); $firma = app(\App\Services\CierreProgramacion::class)->revisar($this->version->fresh())['firma'];
        $this->cerrar('aprobar')->assertSessionHasNoErrors();
        $this->post(route('programacion.version.cerrar', $this->version), ['etapa' => 'aprobar', 'firma' => $firma, 'confirmar' => 1])->assertSessionHasErrors('cierre');
        $this->assertDatabaseCount('cierres_programacion', 1);
    }

    public function test_aprobacion_bloquea_cambios_de_horarios_y_docentes_por_rutas_existentes(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors();
        $this->usuario->givePermissionTo(Permission::findOrCreate('asignar horarios', 'web'));
        $this->delete(route('programacion.asignacion.horarios.eliminar', $this->horario))->assertSessionHasErrors('version_id');
        $this->post(route('programacion.asignacion.profesores.guardar'), ['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => 1])->assertSessionHasErrors('asignacion');
        $this->delete(route('programacion.asignacion.profesores.eliminar', Models\GrupoProfesor::firstOrFail()))->assertSessionHasErrors('asignacion');
        $this->assertDatabaseCount('horarios', 1);
        $this->get(route('programacion.asignacion.profesores', ['periodo_id' => $this->periodo->id, 'seccion_id' => $this->grupo->actividadSeccion->seccion_id]))
            ->assertOk()->assertSee('La programación actual está aprobada o publicada.')->assertDontSee('class="min-w-0 space-y-2 formulario-profesor"', false);
    }

    public function test_lector_autorizado_consulta_copia_sin_poder_publicar(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors(); $this->cerrar('publicar')->assertSessionHasNoErrors();
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->get(route('programacion.version.publicada', $this->version))->assertOk();
        $this->get(route('programacion.version.cierre', $this->version))->assertRedirect();
        $this->post(route('programacion.version.cerrar', $this->version), [])->assertRedirect();
        auth()->logout();
        $this->get(route('programacion.version.publicada', $this->version))->assertRedirect(route('login'));
    }

    public function test_fallo_auditoria_revierte_estado_y_copia_conservada(): void
    {
        $this->aprobar(); $this->withoutExceptionHandling();
        Models\CierreProgramacion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->cerrar('aprobar'); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\CierreProgramacion::flushEventListeners(); }
        $this->assertSame('Borrador', $this->version->fresh()->estado);
        $this->assertDatabaseCount('cierres_programacion', 0);
        $this->assertDatabaseCount('horarios_profesores', 0);
    }

    public function test_copia_publicada_sigue_disponible_aunque_otra_version_sea_actual(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors(); $this->cerrar('publicar')->assertSessionHasNoErrors();
        $this->version->update(['es_actual' => false]);
        $otra = $this->version->replicate(); $otra->version = '1.1'; $otra->estado = 'Borrador'; $otra->es_actual = true; $otra->save();
        $this->get(route('programacion.version.publicada', $this->version))->assertOk()->assertSee('Es una publicación anterior')->assertSee('Backend');
        $this->get(route('programacion.version.publicada', $otra))->assertNotFound();
    }

    public function test_cambio_de_catalogo_despues_de_aprobar_exige_nuevo_borrador(): void
    {
        $this->aprobar(); $this->cerrar('aprobar')->assertSessionHasNoErrors();
        Models\Sala::query()->update(['capacidad' => 40]);
        $this->get(route('programacion.version.cierre', $this->version))->assertOk()->assertSee('Los datos cambiaron desde la aprobación.');
        $this->cerrar('publicar')->assertSessionHasErrors('cierre');
        $this->assertNull($this->version->fresh()->fecha_publicacion);
    }
}
