<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class CambioEspaciosTest extends IsolatedDatabaseTestCase
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

    private function sala(array $cambios = []): Models\Sala
    {
        $actual = Models\Sala::firstOrFail();
        return Models\Sala::create($cambios + ['universidad_id' => $actual->universidad_id, 'sede_id' => $actual->sede_id, 'tipo_sala_id' => $actual->tipo_sala_id,
            'codigo' => 'TH-02', 'nombre' => 'Sala alternativa', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
    }

    private function crear(string $espacio): Models\SolicitudCambioHorario
    {
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario, $espacio);
        $this->assertArrayHasKey($this->horario->dia_id.':'.$this->horario->bloque_horario_id, $c['opciones']);
        $this->post(route('programacion.cambios.store', $this->horario), ['firma' => $c['firma'], 'espacio' => $espacio, 'motivo' => 'Cambio de espacio',
            'fecha_limite' => today()->addDays(7)->toDateString(), 'opciones' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id]])->assertSessionHasNoErrors();
        $s = Models\SolicitudCambioHorario::latest('id')->firstOrFail();
        foreach ($s->respuestasDocentes as $r) {
            $url = URL::temporarySignedRoute('programacion.cambios.responder', $s->fecha_limite, ['respuesta' => $r->id]);
            $this->get($url)->assertOk()->assertSee('Solicitud de cambio');
            $this->post($url, ['decision' => 'aceptar', 'opcion_id' => $s->opciones()->firstOrFail()->id])->assertSessionHasNoErrors();
        }
        return $s;
    }

    public function test_cambio_de_sala_en_el_mismo_modulo_conserva_asignacion_anterior_inactiva(): void
    {
        $destino = $this->sala(); $origen = Models\AsignacionSala::firstOrFail();
        $this->get(route('programacion.cambios.create', ['horario' => $this->horario, 'espacio' => 'sala:'.$destino->id]))->assertOk()->assertSee('Sala alternativa')->assertSee('Destino de las alternativas');
        $s = $this->crear('sala:'.$destino->id); $antes = $this->horario->fresh()->getAttributes();
        $r = $s->respuestasDocentes()->firstOrFail();
        $this->get(URL::temporarySignedRoute('programacion.cambios.responder', $s->fecha_limite, ['respuesta' => $r->id]))->assertOk();
        $this->assertStringContainsString('Sala alternativa', $s->opciones()->firstOrFail()->datos['nombre']);
        $this->assertDatabaseHas('asignaciones_salas', ['id' => $origen->id, 'estado' => true]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_salas', ['id' => $origen->id, 'estado' => false]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $destino->id, 'estado' => true]);
        $this->assertSame($antes['dia_id'], $this->horario->fresh()->dia_id); $this->assertSame($antes['bloque_horario_id'], $this->horario->fresh()->bloque_horario_id);
        $this->assertSame('Aplicada', $s->fresh()->estado);
        $this->assertSame(1, $this->horario->asignacionesSalas()->where('estado', true)->count());
    }

    public function test_no_ofrece_otras_sedes_tipos_inactivos_ni_capacidades_insuficientes(): void
    {
        $apta = $this->sala();
        $sede = Models\Sede::create(['universidad_id' => $apta->universidad_id, 'nombre' => 'Concepción', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Otro tipo', 'estado' => true]);
        $invalidas = [$this->sala(['codigo' => 'PEQ', 'capacidad' => 1]), $this->sala(['codigo' => 'OTRA-SEDE', 'sede_id' => $sede->id]),
            $this->sala(['codigo' => 'INACTIVA', 'estado' => false]), $this->sala(['codigo' => 'NO-USO', 'disponible' => false]), $this->sala(['codigo' => 'OTRO-TIPO', 'tipo_sala_id' => $tipo->id])];
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayHasKey('sala:'.$apta->id, $c['espacios']);
        foreach ($invalidas as $sala) {
            $this->assertArrayNotHasKey('sala:'.$sala->id, $c['espacios']);
            $this->get(route('programacion.cambios.create', ['horario' => $this->horario, 'espacio' => 'sala:'.$sala->id]))->assertSessionHasErrors('espacio');
        }
    }

    public function test_espacio_ocupado_por_otro_curso_en_otro_laboratorio_se_rechaza_al_aplicar(): void
    {
        $destino = $this->sala(); $s = $this->crear('sala:'.$destino->id);
        $seccion = $this->grupo->actividadSeccion->seccion->replicate(); $seccion->codigo = '12'; $seccion->save();
        $actividad = $this->grupo->actividadSeccion->replicate(); $actividad->seccion_id = $seccion->id; $actividad->save();
        $grupo = $this->grupo->replicate(); $grupo->actividad_seccion_id = $actividad->id; $grupo->save();
        $ocupado = $this->horario->replicate(); $ocupado->actividad_seccion_id = $actividad->id; $ocupado->grupo_seccion_id = $grupo->id; $ocupado->save();
        $lab = Models\Laboratorio::create(['sala_id' => $destino->id, 'codigo' => 'LAB-OTRO', 'nombre' => 'Laboratorio ocupado', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        Models\AsignacionLaboratorio::create(['horario_id' => $ocupado->id, 'laboratorio_id' => $lab->id, 'estado' => true]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertDatabaseMissing('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $destino->id]);
    }

    public function test_docente_o_seccion_ocupados_siguen_bloqueando_aunque_cambie_la_sala(): void
    {
        $destino = $this->sala(); $grupo = $this->grupo->replicate(); $grupo->codigo = 'G:2'; $grupo->numero = 2; $grupo->save();
        $ocupado = $this->horario->replicate(); $ocupado->grupo_seccion_id = $grupo->id; $ocupado->save();
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario, 'sala:'.$destino->id);
        $this->assertArrayNotHasKey($this->horario->dia_id.':'.$this->horario->bloque_horario_id, $c['opciones']);
    }

    public function test_cambio_de_capacidad_posterior_invalida_la_opcion_aceptada(): void
    {
        $destino = $this->sala(); $s = $this->crear('sala:'.$destino->id);
        $destino->update(['capacidad' => 40]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertSame('Respondida', $s->fresh()->estado);
        $destino->update(['capacidad' => 1]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('espacio');
    }

    public function test_cambio_de_laboratorio_actualiza_tambien_su_sala_y_conserva_capacidades(): void
    {
        $salaOriginal = Models\Sala::firstOrFail(); $salaDestino = $this->sala();
        $labOriginal = Models\Laboratorio::create(['sala_id' => $salaOriginal->id, 'codigo' => 'LAB-A', 'nombre' => 'Laboratorio A', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        $labDestino = Models\Laboratorio::create(['sala_id' => $salaDestino->id, 'codigo' => 'LAB-B', 'nombre' => 'Laboratorio B', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        Models\AsignacionLaboratorio::create(['horario_id' => $this->horario->id, 'laboratorio_id' => $labOriginal->id, 'capacidad_asignada' => 20, 'es_principal' => true, 'estado' => true]);
        Models\RequerimientoActividad::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'tipo_requerimiento' => 'Laboratorio', 'cantidad' => 1, 'tipo_sala_id' => $salaOriginal->tipo_sala_id, 'obligatorio' => true, 'estado' => true]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayHasKey('laboratorio:'.$labDestino->id, $c['espacios']); $this->assertArrayNotHasKey('sala:'.$salaDestino->id, $c['espacios']);
        $s = $this->crear('laboratorio:'.$labDestino->id);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_laboratorios', ['horario_id' => $this->horario->id, 'laboratorio_id' => $labDestino->id, 'capacidad_asignada' => 20, 'estado' => true]);
        $this->assertDatabaseHas('asignaciones_laboratorios', ['horario_id' => $this->horario->id, 'laboratorio_id' => $labOriginal->id, 'estado' => false]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $salaDestino->id, 'estado' => true]);
    }

    public function test_sin_equipos_en_destino_no_ofrece_sala_aunque_tenga_capacidad(): void
    {
        $actual = Models\Sala::firstOrFail(); $destino = $this->sala();
        $equipo = Models\Equipamiento::create(['universidad_id' => $actual->universidad_id, 'nombre' => 'Computadores', 'tipo' => 'Computación', 'cantidad' => 40, 'estado' => true, 'disponible' => true]);
        Models\EquipamientoEspacio::create(['equipamiento_id' => $equipo->id, 'sala_id' => $actual->id, 'cantidad' => 20]);
        Models\RequerimientoActividad::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $equipo->id, 'cantidad' => 20, 'obligatorio' => true, 'estado' => true]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayNotHasKey('sala:'.$destino->id, $c['espacios']);
        Models\EquipamientoEspacio::create(['equipamiento_id' => $equipo->id, 'sala_id' => $destino->id, 'cantidad' => 20]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertArrayHasKey('sala:'.$destino->id, $c['espacios']);
    }

    public function test_fallo_auditoria_revierte_tambien_los_cambios_de_asignaciones(): void
    {
        $destino = $this->sala(); $origen = Models\AsignacionSala::firstOrFail(); $s = $this->crear('sala:'.$destino->id);
        $this->withoutExceptionHandling();
        Models\SolicitudCambioHorario::updated(function ($s) { if ($s->estado === 'Aplicada') DB::statement('DROP TABLE historial_acciones'); });
        try { $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar']); $this->fail('Se esperaba un fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\SolicitudCambioHorario::flushEventListeners(); }
        $this->assertDatabaseHas('asignaciones_salas', ['id' => $origen->id, 'estado' => true]);
        $this->assertDatabaseMissing('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $destino->id]);
        $this->assertSame('Respondida', $s->fresh()->estado);
    }

    public function test_varios_espacios_conservan_la_distribucion_y_no_permiten_sustitucion_simple(): void
    {
        $otra = $this->sala();
        Models\AsignacionSala::query()->update(['capacidad_asignada' => 10]);
        Models\AsignacionSala::create(['horario_id' => $this->horario->id, 'sala_id' => $otra->id, 'capacidad_asignada' => 10, 'estado' => true]);
        $c = app(\App\Services\AlternativasCambioHorario::class)->preparar($this->horario);
        $this->assertSame(['actual'], array_keys($c['espacios'])); $this->assertNotNull($c['limitacionEspacios']);
        $this->get(route('programacion.cambios.create', $this->horario))->assertOk()->assertSee('varios espacios');
    }

    public function test_laboratorio_movil_se_conserva_al_cambiar_su_sala_compatible(): void
    {
        $actual = Models\Sala::firstOrFail(); $destino = $this->sala();
        $movil = Models\LaboratorioMovil::create(['universidad_id' => $actual->universidad_id, 'sede_id' => $actual->sede_id, 'codigo' => 'MOV-1', 'nombre' => 'Notebooks', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        $vinculo = Models\AsignacionLaboratorioMovil::create(['horario_id' => $this->horario->id, 'laboratorio_movil_id' => $movil->id, 'capacidad_asignada' => 20, 'estado' => true]);
        Models\RequerimientoActividad::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'tipo_requerimiento' => 'Laboratorio móvil', 'tipo_sala_id' => $actual->tipo_sala_id,
            'cantidad' => 1, 'obligatorio' => true, 'estado' => true]);
        $antes = $vinculo->fresh()->getAttributes(); $s = $this->crear('sala:'.$destino->id);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $this->assertSame($antes, $vinculo->fresh()->getAttributes());
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $destino->id, 'estado' => true]);
    }

    public function test_reactiva_vinculo_anterior_sin_duplicarlo_si_se_regresa_a_la_sala_original(): void
    {
        $original = Models\AsignacionSala::firstOrFail(); $destino = $this->sala();
        $s = $this->crear('sala:'.$destino->id);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $otra = $this->crear('sala:'.$original->sala_id);
        $this->post(route('programacion.cambios.revisar', $otra), ['decision' => 'aplicar'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('asignaciones_salas', 2);
        $this->assertDatabaseHas('asignaciones_salas', ['id' => $original->id, 'estado' => true]);
        $this->assertSame(1, $this->horario->asignacionesSalas()->where('estado', true)->count());
    }

    public function test_destino_inventado_y_fuente_publicada_no_permiten_cambios(): void
    {
        $this->get(route('programacion.cambios.create', ['horario' => $this->horario, 'espacio' => 'sala:999999']))->assertSessionHasErrors('espacio');
        $this->get(route('programacion.cambios.create', ['horario' => $this->horario, 'espacio' => '../otra']))->assertSessionHasErrors('espacio');
        $destino = $this->sala(); $s = $this->crear('sala:'.$destino->id); $this->version->update(['estado' => 'Publicado']);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('cambio');
        $this->assertDatabaseMissing('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $destino->id]);
    }

    public function test_cambio_de_estado_o_stock_de_equipos_en_destino_impide_aplicar(): void
    {
        $actual = Models\Sala::firstOrFail(); $destino = $this->sala();
        $equipo = Models\Equipamiento::create(['universidad_id' => $actual->universidad_id, 'nombre' => 'Equipos', 'tipo' => 'Computación', 'cantidad' => 40, 'estado' => true, 'disponible' => true]);
        Models\EquipamientoEspacio::create(['equipamiento_id' => $equipo->id, 'sala_id' => $actual->id, 'cantidad' => 20]);
        $ubicacion = Models\EquipamientoEspacio::create(['equipamiento_id' => $equipo->id, 'sala_id' => $destino->id, 'cantidad' => 20]);
        Models\RequerimientoActividad::create(['actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $equipo->id, 'cantidad' => 20, 'obligatorio' => true, 'estado' => true]);
        $s = $this->crear('sala:'.$destino->id); $ubicacion->update(['cantidad' => 5]);
        $this->post(route('programacion.cambios.revisar', $s), ['decision' => 'aplicar'])->assertSessionHasErrors('espacio');
        $this->assertSame('Respondida', $s->fresh()->estado);
    }
}
