<?php

namespace Tests\Feature;

use App\Models;
use App\Services\{GeneracionCargaPendiente, DistribuidorCargaPendiente, RevisionConflictosProgramacion};
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class GeneracionCargaPendienteTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Sede $sede;
    private Models\Sala $sala;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Coordinación', 'rut' => '17777777', 'email' => 'completar@example.test', 'password' => 'prueba']);
        foreach (['generar programacion semestral', 'ver programacion semestral', 'asignar profesores', 'administrar usuarios', 'gestionar conflictos'] as $p) $this->usuario->givePermissionTo(Permission::findOrCreate($p, 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'version' => 0.1, 'tipo' => 'semestral', 'estado' => 'Borrador', 'es_actual' => true]);
        $this->sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Talcahuano', 'estado' => true]);
        $c = Models\Carrera::create(['universidad_id' => $uni, 'sede_id' => $this->sede->id, 'codigo' => 'INF', 'nombre' => 'Informática', 'estado' => true]);
        $r = Models\Asignatura::create(['universidad_id' => $uni, 'nombre' => 'Backend', 'codigo' => 'BACK', 'estado' => true]);
        $s = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $r->id, 'codigo' => '11', 'semestre' => 2, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]); $s->carreras()->attach($c->id);
        $a = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 2, 'cantidad_estudiantes' => 20, 'estado' => true]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $a->id, 'codigo' => '1', 'numero' => 1, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'rut' => '12345678', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'es_principal' => true, 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true, 'requiere_equipamiento' => false]);
        $this->sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $this->sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'TH-01', 'capacidad' => 30, 'disponible' => true, 'estado' => true]);
    }
    private function slots(): array
    {
        $dias = Models\Dia::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id'); $bloques = Models\BloqueHorario::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id');
        $slots = []; foreach ($dias as $d) foreach ($bloques as $b) $slots[] = $d.':'.$b; return $slots;
    }
    private function aprobar(?Models\Profesor $profesor = null, ?array $slots = null): void
    {
        $profesor ??= $this->profesor;
        $this->post(route('disponibilidad.store'), ['profesor_id' => $profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $s = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $this->post(URL::temporarySignedRoute('disponibilidad.publica', $s->fecha_limite->endOfDay(), ['solicitud' => $s->id]), ['bloques' => $slots ?? $this->slots()])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
    }
    private function datos(array $cambios = []): array { return $cambios + ['version_id' => $this->version->id, 'seleccion' => [$this->grupo->id], 'sedes' => [$this->grupo->id => $this->sede->id], 'duracion' => 3000]; }
    private function generar(array $cambios = []): array
    {
        $r = $this->post(route('programacion.completar.generar'), $this->datos($cambios)); $r->assertSessionHasNoErrors()->assertRedirect();
        $token = basename(parse_url($r->headers->get('Location'), PHP_URL_PATH)); return [$token, session('cargas_pendientes.'.$token)];
    }
    private function guardar(string $token): Models\VersionPlanificacion
    {
        $this->post(route('programacion.completar.guardar', $token), ['confirmar' => 1])->assertSessionHasNoErrors(); return Models\VersionPlanificacion::latest('id')->firstOrFail();
    }
    private function fijo(?Models\GrupoSeccion $g = null, ?Models\Profesor $p = null): Models\Horario
    {
        $g ??= $this->grupo; $p ??= $this->profesor;
        [$d, $b] = array_map('intval', explode(':', $this->slots()[0]));
        $h = Models\Horario::create(['actividad_seccion_id' => $g->actividad_seccion_id, 'grupo_seccion_id' => $g->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => $d, 'bloque_horario_id' => $b, 'estado' => true]);
        $h->profesores()->create(['profesor_id' => $p->id, 'estado' => true, 'es_principal' => true]);
        $h->asignacionesSalas()->create(['sala_id' => $this->sala->id, 'capacidad_asignada' => 20, 'estado' => true]); return $h;
    }

    public function test_completa_borrador_vacio_con_vista_previa_y_revision_global(): void
    {
        $this->aprobar(); $this->get(route('programacion.completar.index'))->assertOk()->assertSee('Backend')->assertSee('Talcahuano');
        [$token] = $this->generar();
        $this->get(route('programacion.completar.show', $token))->assertOk()->assertSee('TH-01')->assertSee('Guardar como borrador nuevo');
        $this->assertDatabaseCount('horarios', 0); $nueva = $this->guardar($token);
        $this->assertFalse($nueva->es_actual); $this->assertTrue($this->version->fresh()->es_actual); $this->assertSame(2, $nueva->horarios()->count());
        $r = app(RevisionConflictosProgramacion::class)->revisar($nueva); $this->assertSame(0, $r['conflictos']); $this->assertSame(0, $r['carga']['pendientes']);
        $this->assertDatabaseCount('asignaciones_salas', 2); $this->assertDatabaseCount('horarios_profesores', 2);
    }
    public function test_modulos_fijos_no_cambian_y_solo_se_completa_lo_que_falta(): void
    {
        $fijo = $this->fijo(); $antes = $fijo->fresh()->getAttributes(); $this->aprobar(); [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertSame($antes, $fijo->fresh()->getAttributes());
        $this->assertSame(2, $nueva->horarios()->count());
        $this->assertSame(1, $nueva->horarios()->where('dia_id', $fijo->dia_id)->where('bloque_horario_id', $fijo->bloque_horario_id)->count());
    }
    public function test_exige_disponibilidad_aprobada_y_no_acepta_la_editada_tras_aprobacion(): void
    {
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->aprobar(); Models\DisponibilidadProfesor::where('profesor_id', $this->profesor->id)->where('tipo_disponibilidad', 'Disponible')->firstOrFail()->update(['tipo_disponibilidad' => 'No disponible']);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion'); $this->assertDatabaseCount('horarios', 0);
    }
    public function test_validacion_de_sede_duracion_y_grupo_ajeno_no_guarda(): void
    {
        $this->aprobar(); $otra = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Otra sede', 'estado' => true]);
        $this->post(route('programacion.completar.generar'), $this->datos(['sedes' => [$this->grupo->id => $otra->id]]))->assertSessionHasErrors('generacion');
        $this->post(route('programacion.completar.generar'), $this->datos(['duracion' => 3601]))->assertSessionHasErrors('generacion');
        $g = $this->grupo->replicate(); $g->estado_activo = false; $g->codigo = '2'; $g->save();
        $this->post(route('programacion.completar.generar'), $this->datos(['seleccion' => [$g->id]]))->assertSessionHasErrors('generacion');
    }
    public function test_cambios_de_datos_invalidan_vista_previa_y_guardado(): void
    {
        $this->aprobar(); [$token] = $this->generar(); $this->sala->update(['capacidad' => 35]);
        $this->get(route('programacion.completar.show', $token))->assertRedirect()->assertSessionHasErrors('generacion');
        $this->post(route('programacion.completar.guardar', $token), ['confirmar' => 1])->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }
    public function test_cierre_de_base_y_archivo_del_periodo_bloquean_generacion(): void
    {
        $this->aprobar(); $this->version->update(['estado' => 'Publicado']);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->version->update(['estado' => 'Borrador']); $this->periodo->update(['estado' => false]);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
    }
    public function test_sesion_vencimiento_confirmacion_y_doble_envio(): void
    {
        $this->aprobar(); [$token] = $this->generar();
        $this->post(route('programacion.completar.guardar', $token))->assertSessionHasErrors('confirmar');
        $this->guardar($token); $this->post(route('programacion.completar.guardar', $token), ['confirmar' => 1])->assertGone();
        $this->assertDatabaseCount('versiones_planificacion', 2);
        [$token] = $this->generar(); $this->travel(31)->minutes(); $this->get(route('programacion.completar.show', $token))->assertGone();
    }
    private function otroGrupo(?Models\Profesor $p = null): Models\GrupoSeccion
    {
        $s = $this->grupo->actividadSeccion->seccion->replicate(); $s->codigo = (string) (Models\Seccion::count() + 11); $s->save();
        $s->carreras()->attach($this->grupo->actividadSeccion->seccion->carreras->pluck('id'));
        $a = $this->grupo->actividadSeccion->replicate(); $a->seccion_id = $s->id; $a->save();
        $g = $this->grupo->replicate(); $g->actividad_seccion_id = $a->id; $g->setRelations([]); $g->save();
        Models\GrupoProfesor::create(['grupo_seccion_id' => $g->id, 'profesor_id' => ($p ?? $this->profesor)->id, 'estado' => true, 'es_principal' => true]);
        return $g;
    }
    private function laboratorio(): Models\Laboratorio
    {
        return Models\Laboratorio::create(['sala_id' => $this->sala->id, 'codigo' => 'LAB-1', 'nombre' => 'Computación', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
    }
    public function test_laboratorio_se_asigna_junto_con_su_sala_fisica(): void
    {
        $this->grupo->actividadSeccion->update(['tipo' => 'Laboratorio']); $lab = $this->laboratorio(); $this->aprobar();
        [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertSame(2, Models\AsignacionLaboratorio::where('laboratorio_id', $lab->id)->count());
        $this->assertSame(2, $nueva->horarios()->whereHas('asignacionesSalas', fn ($s) => $s->where('sala_id', $this->sala->id))->count());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['recursos']);
    }
    public function test_equipamiento_se_comprueba_en_la_ubicacion_real_del_espacio(): void
    {
        $a = $this->grupo->actividadSeccion;
        $e = Models\Equipamiento::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Computadores', 'tipo' => 'Computación', 'cantidad' => 20, 'estado' => true, 'disponible' => true]);
        $a->requerimientos()->create(['tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $e->id, 'cantidad' => 20, 'obligatorio' => true, 'estado' => true]);
        $this->aprobar();
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $e->ubicaciones()->create(['sala_id' => $this->sala->id, 'cantidad' => 20]);
        [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['recursos']);
        $this->assertSame(20, $e->fresh()->cantidad); $this->assertDatabaseCount('equipamientos_espacios', 1);
    }
    public function test_laboratorio_movil_requiere_sala_compatible_y_capacidad(): void
    {
        $this->grupo->actividadSeccion->requerimientos()->create(['tipo_requerimiento' => 'Laboratorio móvil', 'tipo_sala_id' => $this->sala->tipo_sala_id, 'cantidad' => 1, 'obligatorio' => true, 'estado' => true]);
        $m = Models\LaboratorioMovil::create(['universidad_id' => $this->periodo->universidad_id, 'sede_id' => $this->sede->id, 'codigo' => 'MOV-1', 'nombre' => 'Carro', 'capacidad' => 10, 'estado' => true, 'disponible' => true]);
        $this->aprobar(); $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $m->update(['capacidad' => 25]); [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertDatabaseCount('asignaciones_laboratorios_moviles', 2);
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['capacidad']);
    }
    public function test_grupos_comparten_ocupacion_de_sala_fisica_y_laboratorio(): void
    {
        $otroProfesor = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432', 'nombre' => 'Pedro', 'apellido' => 'Soto', 'estado' => true]);
        $otro = $this->otroGrupo($otroProfesor); $otro->actividadSeccion->update(['cantidad_horas' => 1]);
        $lab = $this->laboratorio(); $fijo = $this->fijo($otro, $otroProfesor); $fijo->asignacionesSalas()->delete();
        $fijo->asignacionesLaboratorios()->create(['laboratorio_id' => $lab->id, 'estado' => true, 'capacidad_asignada' => 20]);
        $this->aprobar(slots: array_slice($this->slots(), 0, 2));
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]); [$token] = $this->generar(); $nueva = $this->guardar($token);
        $nuevo = $nueva->horarios()->where('grupo_seccion_id', $this->grupo->id)->sole();
        $this->assertNotSame($fijo->bloque_horario_id, $nuevo->bloque_horario_id);
    }
    public function test_varios_docentes_exigen_disponibilidad_comun(): void
    {
        $p = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432', 'nombre' => 'Pedro', 'apellido' => 'Soto', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->aprobar(); $this->aprobar($p, [$this->slots()[0]]);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->aprobar($p, array_slice($this->slots(), 0, 2)); [$token] = $this->generar(); $this->guardar($token);
        $this->assertDatabaseCount('horarios_profesores', 4);
    }
    public function test_conserva_docentes_exclusivos_de_modulos_y_usa_plantilla_para_los_nuevos(): void
    {
        $p = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432', 'nombre' => 'Pedro', 'apellido' => 'Soto', 'estado' => true]);
        $fijo = $this->fijo(p: $p); $this->aprobar(); [$token] = $this->generar();
        $this->get(route('programacion.completar.show', $token))->assertOk()->assertSee('observaciones de disponibilidad');
        $nueva = $this->guardar($token);
        $this->assertSame(1, Models\HorarioProfesor::where('profesor_id', $p->id)->whereHas('horario', fn ($h) => $h->where('version_planificacion_id', $nueva->id))->count());
        $this->assertSame(1, Models\HorarioProfesor::where('profesor_id', $this->profesor->id)->whereHas('horario', fn ($h) => $h->where('version_planificacion_id', $nueva->id))->count());
        $this->assertSame($p->id, $fijo->profesores()->sole()->profesor_id);
    }
    public function test_generacion_progresiva_continua_desde_borrador_no_actual(): void
    {
        $otro = $this->otroGrupo(); $this->aprobar(); [$token] = $this->generar(); $primera = $this->guardar($token);
        $r = app(RevisionConflictosProgramacion::class)->revisar($primera); $this->assertSame(2, $r['carga']['pendientes']);
        [$token] = $this->generar(['version_id' => $primera->id, 'seleccion' => [$otro->id], 'sedes' => [$otro->id => $this->sede->id]]); $segunda = $this->guardar($token);
        $this->assertSame(4, $segunda->horarios()->count()); $this->assertSame(2, $primera->horarios()->count());
        $this->assertTrue($this->version->fresh()->es_actual); $this->assertFalse($segunda->es_actual);
        $r = app(RevisionConflictosProgramacion::class)->revisar($segunda); $this->assertSame(0, $r['carga']['pendientes']); $this->assertSame(0, $r['conflictos']);
    }
    public function test_cambios_de_carga_fuera_de_la_seleccion_invalidan_el_resumen(): void
    {
        $otro = $this->otroGrupo(); $this->aprobar(); [$token] = $this->generar(); $otro->actividadSeccion->update(['cantidad_horas' => 4]);
        $this->get(route('programacion.completar.show', $token))->assertRedirect()->assertSessionHasErrors('generacion');
    }
    public function test_almuerzo_se_excluye_por_intervalo_aunque_no_este_marcado(): void
    {
        $b = Models\BloqueHorario::create(['numero' => 20, 'hora_inicio' => '13:00:00', 'hora_fin' => '13:50:00', 'es_almuerzo' => false, 'estado' => true]);
        $d = Models\Dia::where('numero', 1)->value('id'); $this->aprobar(slots: [$d.':'.$b->id]);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion'); $this->assertDatabaseCount('horarios', 0);
    }
    public function test_grupo_sin_cupos_puede_usar_referencia_con_unico_grupo_activo(): void
    {
        $this->grupo->update(['cupos' => null, 'inscritos' => 0]);
        $inactivo = $this->grupo->replicate(); $inactivo->codigo = '2'; $inactivo->estado_activo = false; $inactivo->save();
        $this->aprobar(); [$token] = $this->generar(); $this->guardar($token); $this->assertDatabaseCount('horarios', 2);
    }
    public function test_plan_manipulado_no_se_guarda_y_el_token_no_se_comparte_con_otro_usuario(): void
    {
        $this->aprobar(); [$token, $p] = $this->generar(); $p['plan'][array_key_first($p['plan'])]['paquete'] = 'ajeno';
        $this->withSession(['cargas_pendientes' => [$token => $p]])->post(route('programacion.completar.guardar', $token), ['confirmar' => 1])->assertSessionHasErrors('generacion');
        $p['usuario_id'] = $this->usuario->id + 1;
        $this->withSession(['cargas_pendientes' => [$token => $p]])->get(route('programacion.completar.show', $token))->assertGone();
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }
    public function test_servicio_reconoce_propuesta_ya_guardada_y_fallo_auditoria_revierte_todo(): void
    {
        $this->aprobar(); [$token, $p] = $this->generar(); $nueva = $this->guardar($token);
        $misma = app(GeneracionCargaPendiente::class)->guardar($this->version, $p, $this->usuario->id, null);
        $this->assertSame($nueva->id, $misma->id); $this->assertDatabaseCount('versiones_planificacion', 2);
        [$token] = $this->generar(); $this->withoutExceptionHandling();
        Models\VersionPlanificacion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post(route('programacion.completar.guardar', $token), ['confirmar' => 1]); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\VersionPlanificacion::flushEventListeners(); }
        $this->assertDatabaseCount('versiones_planificacion', 2); $this->assertDatabaseCount('horarios', 2);
    }

    public function test_prioridad_por_ramo_y_laboratorio_se_aplica_a_los_grupos_generados(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]); $this->laboratorio();
        $p = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432', 'nombre' => 'Pedro', 'apellido' => 'Soto', 'estado' => true]);
        $otro = $this->otroGrupo($p); $s = $otro->actividadSeccion->seccion;
        $ramo = $s->asignatura->replicate(); $ramo->codigo = 'GEN'; $ramo->nombre = 'Otro ramo'; $ramo->save(); $s->update(['asignatura_id' => $ramo->id]);
        foreach ([[$this->grupo, 1], [$otro, 2]] as [$g, $nivel]) {
            $a = $g->fresh()->actividadSeccion;
            $a->requerimientos()->create(['tipo_requerimiento' => 'Laboratorio', 'cantidad' => 1, 'obligatorio' => true, 'estado' => true]);
            Models\PrioridadRecurso::create(['nombre' => 'Prioridad '.$nivel, 'asignatura_id' => $a->seccion->asignatura_id, 'tipo_recurso' => 'laboratorio', 'clave_recurso' => 'laboratorio:todos', 'nivel' => $nivel, 'estado' => true]);
        }
        $slots = array_slice($this->slots(), 0, 2); $this->aprobar(slots: $slots); $this->aprobar($p, $slots);
        [$token] = $this->generar(['seleccion' => [$otro->id, $this->grupo->id], 'sedes' => [$otro->id => $this->sede->id, $this->grupo->id => $this->sede->id]]);
        $nueva = $this->guardar($token); [$d, $b] = explode(':', $slots[0]);
        $this->assertSame((int) $b, $nueva->horarios()->where('grupo_seccion_id', $this->grupo->id)->sole()->bloque_horario_id);
        $this->assertNotSame((int) $b, $nueva->horarios()->where('grupo_seccion_id', $otro->id)->sole()->bloque_horario_id);
    }
    public function test_movil_no_se_reserva_dos_veces_aunque_haya_salas_diferentes(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $otraSala = $this->sala->replicate(); $otraSala->codigo = 'TH-02'; $otraSala->save();
        $p = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432', 'nombre' => 'Pedro', 'apellido' => 'Soto', 'estado' => true]);
        $otro = $this->otroGrupo($p);
        foreach ([$this->grupo, $otro] as $g) $g->actividadSeccion->requerimientos()->create(['tipo_requerimiento' => 'Laboratorio móvil', 'tipo_sala_id' => $this->sala->tipo_sala_id, 'cantidad' => 1, 'obligatorio' => true, 'estado' => true]);
        Models\LaboratorioMovil::create(['universidad_id' => $this->periodo->universidad_id, 'sede_id' => $this->sede->id, 'codigo' => 'MOV', 'nombre' => 'Carro', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        $slots = [$this->slots()[0]]; $this->aprobar(slots: $slots); $this->aprobar($p, $slots);
        $this->post(route('programacion.completar.generar'), $this->datos(['seleccion' => [$otro->id, $this->grupo->id], 'sedes' => [$otro->id => $this->sede->id, $this->grupo->id => $this->sede->id]]))->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('horarios', 0);
    }
    public function test_no_cambia_sede_de_un_grupo_que_ya_tiene_modulos(): void
    {
        $this->fijo(); $this->aprobar();
        $otra = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Concepción', 'estado' => true]);
        $this->grupo->actividadSeccion->seccion->carreras->first()->sedes()->attach($otra->id);
        $this->post(route('programacion.completar.generar'), $this->datos(['sedes' => [$this->grupo->id => $otra->id]]))->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('horarios', 1);
    }
    public function test_seccion_compartida_necesita_sede_comun_a_todas_sus_carreras(): void
    {
        $otra = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Concepción', 'estado' => true]);
        $c = Models\Carrera::create(['universidad_id' => $this->periodo->universidad_id, 'sede_id' => $otra->id, 'nombre' => 'Otra carrera', 'estado' => true]);
        $this->grupo->actividadSeccion->seccion->carreras()->attach($c->id); $this->aprobar();
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $c->sedes()->attach($this->sede->id); [$token] = $this->generar(); $this->guardar($token); $this->assertDatabaseCount('horarios', 2);
    }
    public function test_intervalos_superpuestos_no_se_admiten_por_tener_otro_identificador(): void
    {
        $this->fijo(); $b = Models\BloqueHorario::create(['numero' => 20, 'hora_inicio' => '08:30:00', 'hora_fin' => '09:20:00', 'estado' => true]);
        $d = Models\Dia::where('numero', 1)->value('id'); $this->aprobar(slots: [$this->slots()[0], $d.':'.$b->id]);
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('horarios', 1);
    }
    public function test_docentes_de_seccion_sirven_si_no_hay_plantilla_de_grupo(): void
    {
        $this->grupo->profesores()->delete();
        $this->grupo->actividadSeccion->seccion->profesores()->create(['profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $this->aprobar(); [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertSame(2, Models\HorarioProfesor::whereHas('horario', fn ($h) => $h->where('version_planificacion_id', $nueva->id))->count());
    }
    public function test_docente_exclusivo_de_modulo_no_define_la_plantilla_para_carga_nueva(): void
    {
        $this->fijo(); $this->grupo->profesores()->delete(); $this->aprobar();
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('horarios', 1);
    }
    public function test_grupo_de_otro_periodo_no_puede_incluirse(): void
    {
        $otro = $this->otroGrupo(); $p = $this->periodo->replicate(); $p->nombre = '2027-1'; $p->anio = 2027; $p->save();
        $otro->actividadSeccion->seccion->update(['periodo_academico_id' => $p->id]); $this->aprobar();
        $this->post(route('programacion.completar.generar'), $this->datos(['seleccion' => [$otro->id], 'sedes' => [$otro->id => $this->sede->id]]))->assertSessionHasErrors('generacion');
    }
    public function test_base_incompleta_en_recursos_no_se_reutiliza_como_si_estuviera_lista(): void
    {
        $h = $this->fijo(); $h->asignacionesSalas()->delete(); $this->aprobar();
        $this->post(route('programacion.completar.generar'), $this->datos())->assertSessionHasErrors('generacion');
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }
    public function test_se_puede_completar_un_grupo_aunque_otro_todavia_no_tenga_docentes(): void
    {
        $otro = $this->otroGrupo(); $otro->profesores()->delete(); $this->aprobar();
        [$token] = $this->generar(); $nueva = $this->guardar($token);
        $this->assertSame(2, $nueva->horarios()->count()); $this->assertSame(2, app(RevisionConflictosProgramacion::class)->revisar($nueva)['carga']['pendientes']);
    }

    public function test_programacion_generada_puede_activarse_aprobarse_y_publicarse(): void
    {
        $this->aprobar(); [$token] = $this->generar(); $nueva = $this->guardar($token);
        $activacion = app(\App\Services\ActivacionVersion::class); $revision = $activacion->revisar($nueva);
        $this->assertSame([], $revision['bloqueos']);
        $nueva = $activacion->activar($nueva, $revision['firma'], $this->usuario->id, null)->fresh();
        $cierre = app(\App\Services\CierreProgramacion::class); $revision = $cierre->revisar($nueva);
        $this->assertSame([], $revision['bloqueos']);
        $nueva = $cierre->cambiar($nueva, 'aprobar', $revision['firma'], $this->usuario->id, null);
        $revision = $cierre->revisar($nueva->fresh()); $this->assertSame([], $revision['bloqueos']);
        $publicada = $cierre->cambiar($nueva, 'publicar', $revision['firma'], $this->usuario->id, null);
        $this->assertSame('Publicado', $publicada->estado); $this->assertSame(2, $publicada->horarios()->count());
        $this->assertFalse($this->version->fresh()->es_actual); $this->assertSame(0, $this->version->horarios()->count());
        $this->get(route('programacion.version.publicada', $publicada))->assertOk()->assertSee('Backend');
        $this->assertDatabaseCount('cierres_programacion', 2);
    }

    public function test_permisos_y_formularios_malformados(): void
    {
        $this->get(route('programacion.completar.index', ['q' => ['incorrecto']]))->assertSessionHasErrors('q');
        $this->post(route('programacion.completar.generar'), $this->datos(['seleccion' => 'incorrecto']))->assertSessionHasErrors('seleccion');
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->get(route('programacion.completar.index'))->assertRedirect(); $this->post(route('programacion.completar.generar'), $this->datos())->assertRedirect();
        auth()->logout(); $this->get(route('programacion.completar.index'))->assertRedirect(route('login'));
    }
}
