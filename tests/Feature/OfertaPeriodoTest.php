<?php

namespace Tests\Feature;

use App\Models;
use App\Services\{GestionOfertaPeriodo, RevisionCargaProgramacion};
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class OfertaPeriodoTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\Asignatura $ramo;
    private Models\Carrera $carrera;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'oferta-periodo@example.test', 'password' => 'prueba']);
        foreach (['administrar usuarios', 'generar programacion semestral', 'ver programacion semestral', 'asignar profesores', 'asignar horarios', 'gestionar conflictos'] as $p) $this->usuario->givePermissionTo(Permission::findOrCreate($p, 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->ramo = Models\Asignatura::create(['universidad_id' => $uni, 'nombre' => 'Backend', 'codigo' => 'BACK', 'horas_catedra' => 2, 'horas_laboratorio' => 3, 'estado' => true]);
        $s = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $uni, 'sede_id' => $s->id, 'nombre' => 'Informática', 'codigo' => 'INF', 'estado' => true]);
    }
    private function ruta(string $accion, array|Models\Seccion $parametros = []): string { return route('administracion.secciones.'.$accion, $parametros); }
    private function datosSeccion(array $cambios = []): array
    {
        return $cambios + ['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $this->ramo->id, 'codigo' => '11', 'semestre' => 2,
            'cupos' => 40, 'inscritos' => 32, 'carreras' => [$this->carrera->id], 'estado_activo' => 1, 'usar_referencia' => 1];
    }
    private function seccion(array $datos = []): Models\Seccion
    {
        $this->post($this->ruta('store'), $this->datosSeccion($datos))->assertSessionHasNoErrors();
        return Models\Seccion::latest('id')->firstOrFail();
    }
    private function revision($registro, array $datos): array { return ['revision' => app(GestionOfertaPeriodo::class)->firma($registro->fresh())] + $datos; }
    private function datosActividad(Models\ActividadSeccion $a, array $cambios = []): array
    {
        return $this->revision($a, $cambios + $a->only(['tipo', 'cantidad_horas', 'cantidad_estudiantes', 'modalidad', 'observaciones', 'estado']));
    }
    private function datosGrupo(Models\GrupoSeccion $g, array $cambios = []): array
    {
        return $this->revision($g, $cambios + $g->only(['codigo', 'nombre', 'numero', 'cupos', 'inscritos', 'estado_activo']));
    }
    private function version(string $estado = 'Borrador', bool $actual = true): Models\VersionPlanificacion
    {
        return Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'version' => 0.1, 'tipo' => 'semestral', 'estado' => $estado, 'es_actual' => $actual, 'usuario_id' => $this->usuario->id]);
    }
    private function horario(Models\Seccion $s, ?Models\VersionPlanificacion $v = null): Models\Horario
    {
        $a = $s->actividades()->where('tipo', 'Cátedra')->firstOrFail();
        return Models\Horario::create(['actividad_seccion_id' => $a->id, 'grupo_seccion_id' => $a->grupos()->value('id'), 'version_planificacion_id' => ($v ?? $this->version())->id,
            'dia_id' => Models\Dia::value('id'), 'bloque_horario_id' => Models\BloqueHorario::value('id'), 'estado' => true]);
    }

    public function test_alta_desde_referencia_crea_actividades_grupos_y_carreras_atomicamente(): void
    {
        $otra = $this->carrera->replicate(); $otra->nombre = 'Analista'; $otra->codigo = 'ANA'; $otra->save();
        $s = $this->seccion(['carreras' => [$this->carrera->id, $otra->id]]);
        $this->assertCount(2, $s->actividades); $this->assertCount(2, $s->grupos); $this->assertCount(2, $s->carreras);
        $this->assertSame(3, (int) $s->actividades()->where('tipo', 'Laboratorio')->value('cantidad_horas'));
        $this->assertDatabaseCount('horarios', 0); $this->assertDatabaseCount('grupos_profesores', 0); $this->assertDatabaseCount('historial_acciones', 1);
        $this->get($this->ruta('show', $s))->assertOk()->assertSee('5')->assertSee('Actividades y grupos');
        $this->get($this->ruta('index'))->assertOk()->assertSee('Backend');
        $this->get($this->ruta('edit', $s))->assertOk()->assertSee('Guardar cambios');
    }

    public function test_tambien_admite_carga_manual_sin_referencia(): void
    {
        $s = $this->seccion(['usar_referencia' => 0]);
        $this->assertDatabaseCount('actividades_seccion', 0);
        $this->get($this->ruta('actividad.create', $s))->assertOk();
        $this->post($this->ruta('actividad.store', $s), ['tipo' => 'Taller', 'cantidad_horas' => 4, 'cantidad_estudiantes' => 20, 'estado' => 1])->assertSessionHasNoErrors();
        $a = $s->actividades()->sole();
        $this->get($this->ruta('grupo.create', ['seccion' => $s->id, 'actividad' => $a->id]))->assertOk();
        $this->post($this->ruta('grupo.store', ['seccion' => $s->id, 'actividad' => $a->id]), ['codigo' => 'A', 'numero' => 1, 'cupos' => 20, 'inscritos' => 16, 'estado_activo' => 1])->assertSessionHasNoErrors();
        $g = $a->grupos()->sole();
        $this->get($this->ruta('grupo.edit', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]))->assertOk()->assertSee('Editar grupo');
        $this->get($this->ruta('actividad.edit', ['seccion' => $s->id, 'id' => $a->id]))->assertOk()->assertSee('Editar actividad');
    }

    public function test_no_relaciona_ramos_o_carreras_de_otra_institucion(): void
    {
        $u = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTR', 'estado' => true]);
        $r = Models\Asignatura::create(['universidad_id' => $u->id, 'codigo' => 'AJENO', 'nombre' => 'Ajeno', 'estado' => true]);
        $c = $this->carrera->replicate(); $c->universidad_id = $u->id; $c->save();
        $this->post($this->ruta('store'), $this->datosSeccion(['asignatura_id' => $r->id]))->assertSessionHasErrors('asignatura_id');
        $this->post($this->ruta('store'), $this->datosSeccion(['carreras' => [$c->id]]))->assertSessionHasErrors('carreras');
        $this->assertDatabaseCount('secciones', 0);
    }

    public function test_padres_inactivos_no_admiten_alta_y_vinculos_previos_se_conservan(): void
    {
        $s = $this->seccion(); $this->ramo->update(['estado' => false]); $this->carrera->update(['estado' => false]);
        $this->post($this->ruta('store'), $this->datosSeccion(['codigo' => '12']))->assertSessionHasErrors('asignatura_id');
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['nombre' => 'Conservada'])))->assertSessionHasNoErrors();
        $this->get($this->ruta('edit', $s))->assertOk()->assertSee('Backend')->assertSee('Informática');
    }

    public function test_duplicados_inactivos_y_codigos_segun_ramo_y_periodo(): void
    {
        $s = $this->seccion(); $s->update(['estado_activo' => false]);
        $this->post($this->ruta('store'), $this->datosSeccion(['codigo' => ' 11 ']))->assertSessionHasErrors('codigo');
        $r = $this->ramo->replicate(); $r->codigo = 'OTRO'; $r->save();
        $this->post($this->ruta('store'), $this->datosSeccion(['asignatura_id' => $r->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('secciones', 2);
    }

    public function test_no_cambia_periodo_ni_ramo_de_seccion_existente(): void
    {
        $s = $this->seccion(); $p = $this->periodo->replicate(); $p->nombre = '2027-2'; $p->anio = 2027; $p->save();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['periodo_academico_id' => $p->id])))->assertSessionHasErrors('seccion');
        $r = $this->ramo->replicate(); $r->codigo = 'OTRO'; $r->save();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['asignatura_id' => $r->id])))->assertSessionHasErrors('seccion');
    }

    public function test_no_duplica_actividad_ni_codigo_o_numero_de_grupo(): void
    {
        $s = $this->seccion(); $a = $s->actividades()->where('tipo', 'Cátedra')->firstOrFail();
        $this->post($this->ruta('actividad.store', $s), ['tipo' => 'Cátedra', 'cantidad_horas' => 1, 'estado' => 1])->assertSessionHasErrors('tipo');
        $ruta = $this->ruta('grupo.store', ['seccion' => $s->id, 'actividad' => $a->id]);
        $this->post($ruta, ['codigo' => '1', 'numero' => 2, 'cupos' => 10, 'inscritos' => 0, 'estado_activo' => 1])->assertSessionHasErrors('codigo');
        $this->post($ruta, ['codigo' => '2', 'numero' => 1, 'cupos' => 10, 'inscritos' => 0, 'estado_activo' => 1])->assertSessionHasErrors('numero');
        $this->post($ruta, ['codigo' => '2', 'numero' => 2, 'cupos' => 10, 'inscritos' => 0, 'estado_activo' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, $a->grupos()->count());
    }

    public function test_rutas_anidadas_no_permiten_tocar_otras_secciones_o_actividades(): void
    {
        $s = $this->seccion(); $otra = $this->seccion(['codigo' => '12']); $a = $otra->actividades()->firstOrFail(); $g = $a->grupos()->firstOrFail();
        $this->get($this->ruta('actividad.edit', ['seccion' => $s->id, 'id' => $a->id]))->assertNotFound();
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $this->datosActividad($a))->assertNotFound();
        $this->get($this->ruta('grupo.edit', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]))->assertNotFound();
        $propia = $s->actividades()->firstOrFail();
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $propia->id, 'id' => $g->id]), $this->datosGrupo($g))->assertNotFound();
    }

    public function test_capacidades_y_limites_se_validan_sin_cambiar_otros_datos(): void
    {
        $this->post($this->ruta('store'), $this->datosSeccion(['cupos' => 20, 'inscritos' => 21]))->assertSessionHasErrors('inscritos');
        $s = $this->seccion(); $a = $s->actividades()->firstOrFail(); $g = $a->grupos()->firstOrFail();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['cupos' => 35])))->assertSessionHasErrors('cupos');
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $this->datosGrupo($g, ['cupos' => 50]))->assertSessionHasErrors('cupos');
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $this->datosActividad($a, ['cantidad_horas' => 256]))->assertSessionHasErrors('cantidad_horas');
        $this->assertSame(40, (int) $s->fresh()->cupos);
    }

    public function test_horarios_actuales_impiden_inactivar_y_reducir_carga_por_debajo_de_asignada(): void
    {
        $s = $this->seccion(); $h = $this->horario($s); $a = $h->actividadSeccion; $g = $h->grupoSeccion;
        Models\Horario::create($h->only(['actividad_seccion_id', 'grupo_seccion_id', 'version_planificacion_id', 'dia_id', 'estado']) + ['bloque_horario_id' => Models\BloqueHorario::whereKeyNot($h->bloque_horario_id)->value('id')]);
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['estado_activo' => 0])))->assertSessionHasErrors('estado_activo');
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $this->datosActividad($a, ['estado' => 0]))->assertSessionHasErrors('estado');
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $this->datosGrupo($g, ['estado_activo' => 0]))->assertSessionHasErrors('estado_activo');
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $this->datosActividad($a, ['cantidad_horas' => 1]))->assertSessionHasErrors('cantidad_horas');
        $this->assertDatabaseCount('horarios', 2);
    }

    public function test_sin_horarios_actuales_admite_desactivar_y_conserva_historial(): void
    {
        $s = $this->seccion(); $this->horario($s, $this->version('Borrador', false));
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['estado_activo' => 0])))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('actividades_seccion', 2); $this->assertDatabaseCount('grupos_seccion', 2);
    }

    public function test_uso_historico_conserva_codigo_nivel_y_carreras(): void
    {
        $s = $this->seccion(); $this->horario($s, $this->version('Borrador', false));
        foreach (['codigo' => '12', 'semestre' => 3] as $campo => $valor) $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion([$campo => $valor])))->assertSessionHasErrors('seccion');
        $c = $this->carrera->replicate(); $c->nombre = 'Otra'; $c->codigo = 'OTR'; $c->save();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['carreras' => [$c->id]])))->assertSessionHasErrors('seccion');
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['carreras' => [$this->carrera->id, $c->id]])))->assertSessionHasNoErrors();
    }

    public function test_definiciones_de_version_publicada_se_conservan_aunque_haya_nuevo_borrador(): void
    {
        $s = $this->seccion(); $h = $this->horario($s, $this->version('Publicado', false)); $this->version();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['nombre' => 'Cambio'])))->assertSessionHasErrors('seccion');
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $h->actividad_seccion_id]), $this->datosActividad($h->actividadSeccion, ['cantidad_horas' => 4]))->assertSessionHasErrors('actividad');
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $h->actividad_seccion_id, 'id' => $h->grupo_seccion_id]), $this->datosGrupo($h->grupoSeccion, ['nombre' => 'Cambio']))->assertSessionHasErrors('grupo');
        $this->get($this->ruta('show', $s))->assertOk()->assertDontSee('Editar sección')->assertSee('versiones aprobadas o publicadas');
    }

    public function test_periodo_archivado_o_actual_cerrada_impide_escritura_y_admite_consulta(): void
    {
        $s = $this->seccion(); $this->periodo->update(['estado' => false]);
        $this->post($this->ruta('store'), $this->datosSeccion(['codigo' => '12']))->assertSessionHasErrors('periodo');
        $this->get($this->ruta('index'))->assertOk()->assertSee('Archivado');
        $this->periodo->update(['estado' => true]); $this->version('Aprobado');
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['nombre' => 'Cambio'])))->assertSessionHasErrors('periodo');
        $this->get($this->ruta('show', $s))->assertOk()->assertDontSee('Editar sección');
    }

    public function test_no_permite_iniciar_borrador_sin_oferta_preparada_o_sin_confirmacion(): void
    {
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->seccion(['usar_referencia' => 0]);
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id])->assertSessionHasErrors('confirmar');
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_borrador_vacio_no_copia_horarios_y_doble_envio_no_crea_otro(): void
    {
        $s = $this->seccion();
        $anterior = $this->version('Borrador', false); $anterior->update(['tipo' => 'anterior', 'es_actual' => true]); $this->horario($s, $anterior);
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasNoErrors();
        $v = Models\VersionPlanificacion::where('tipo', 'semestral')->sole();
        $this->assertSame('0.1', $v->version); $this->assertTrue($v->es_actual); $this->assertSame('Borrador', $v->estado); $this->assertSame(0, $v->horarios()->count());
        $this->assertTrue($anterior->fresh()->es_actual);
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasErrors('version');
        $this->assertDatabaseCount('versiones_planificacion', 2); $this->assertDatabaseCount('horarios', 1);
        $carga = app(RevisionCargaProgramacion::class)->revisar($v, collect());
        $this->assertSame(5, $carga['pendientes']);
    }

    public function test_inicia_numeracion_despues_de_los_borradores_no_actuales(): void
    {
        $this->seccion(); $this->version('Borrador', false)->update(['version' => 1.2]);
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasNoErrors();
        $this->assertSame('1.3', Models\VersionPlanificacion::where('es_actual', true)->sole()->version);
    }

    public function test_flujo_desde_seccion_nueva_hasta_docente_y_modulo_manual(): void
    {
        $s = $this->seccion(); $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasNoErrors();
        $v = Models\VersionPlanificacion::sole(); $a = $s->actividades()->where('tipo', 'Cátedra')->firstOrFail(); $g = $a->grupos()->sole();
        $this->post(route('administracion.docentes.store'), ['universidad_id' => $this->periodo->universidad_id, 'rut' => '12345678', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'estado' => 1])->assertSessionHasNoErrors();
        $p = Models\Profesor::where('rut', '12345678')->sole();
        $this->post(route('programacion.asignacion.profesores.guardar'), ['grupo_seccion_id' => $g->id, 'profesor_id' => $p->id, 'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => 1])->assertSessionHasNoErrors();
        $this->get(route('programacion.asignacion.profesores', ['periodo_id' => $this->periodo->id, 'seccion_id' => $s->id]))->assertOk()->assertSee('Backend');
        $this->post(route('programacion.asignacion.horarios.guardar'), ['grupo_seccion_id' => $g->id, 'version_id' => $v->id, 'dia_id' => Models\Dia::value('id'), 'profesores_ids' => [$p->id], 'bloques_horarios' => [Models\BloqueHorario::value('id')]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('horarios_profesores', 1);
        $this->get(route('programacion.asignacion.horarios', ['periodo_id' => $this->periodo->id, 'seccion_id' => $s->id]))->assertOk()->assertSee('Backend');
        $carga = app(RevisionCargaProgramacion::class)->revisar($v, $v->horarios);
        $this->assertSame(4, $carga['pendientes']);
    }

    public function test_formulario_desactualizado_no_sobrescribe_cambios(): void
    {
        $s = $this->seccion(); $datos = $this->revision($s, $this->datosSeccion(['nombre' => 'Mi cambio'])); $s->update(['nombre' => 'Otro cambio']);
        $this->put($this->ruta('update', $s), $datos)->assertSessionHasErrors('revision');
        $a = $s->actividades()->firstOrFail(); $datosA = $this->datosActividad($a); $a->update(['cantidad_horas' => 6]);
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $datosA)->assertSessionHasErrors('revision');
        $g = $a->grupos()->firstOrFail(); $datosG = $this->datosGrupo($g); $g->update(['nombre' => 'Otro grupo']);
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $datosG)->assertSessionHasErrors('revision');
        $this->assertSame('Otro cambio', $s->fresh()->nombre);
    }

    public function test_guardar_sin_cambios_no_duplica_auditoria_ni_actividades(): void
    {
        $s = $this->seccion();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion()))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 1); $this->assertDatabaseCount('actividades_seccion', 2);
    }

    public function test_campos_malformados_no_rompen_formularios(): void
    {
        $url = $this->ruta('create');
        $this->from($url)->post($this->ruta('store'), $this->datosSeccion(['codigo' => ['inválido'], 'carreras' => 'inválido']))->assertSessionHasErrors(['codigo', 'carreras']);
        $this->get($url)->assertOk();
        $s = $this->seccion(); $a = $s->actividades()->firstOrFail();
        $this->get($this->ruta('grupo.create', ['seccion' => $s->id, 'actividad' => $a->id]))->assertOk();
    }

    public function test_recupera_las_carreras_y_la_preparacion_manual_tras_un_error(): void
    {
        $otra = $this->carrera->replicate();
        $otra->nombre = 'Analista';
        $otra->codigo = 'ANA';
        $otra->save();

        $url = $this->ruta('create');
        $this->from($url)
            ->post($this->ruta('store'), $this->datosSeccion([
                'codigo' => '',
                'carreras' => [$this->carrera->id, $otra->id],
                'usar_referencia' => 0,
            ]))
            ->assertSessionHasErrors('codigo');

        $respuesta = $this->get($url)->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $campos = new \DOMXPath($dom);

        $this->assertSame(2, $campos->query('//input[@name="carreras[]" and @checked]')->length);
        $this->assertSame(0, $campos->query('//input[@name="usar_referencia" and @type="checkbox" and @checked]')->length);
        $this->assertSame(1, $campos->query('//input[@id="buscar-carreras"]')->length);
    }

    public function test_al_editar_no_restaura_carreras_que_el_usuario_desmarco_antes_del_error(): void
    {
        $seccion = $this->seccion();
        $url = $this->ruta('edit', $seccion);
        $datos = $this->revision($seccion, $this->datosSeccion());
        unset($datos['carreras']);

        $this->from($url)->put($this->ruta('update', $seccion), $datos)
            ->assertSessionHasErrors('carreras');

        $respuesta = $this->get($url)->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $campos = new \DOMXPath($dom);

        $this->assertSame(0, $campos->query('//input[@name="carreras[]" and @checked]')->length);
        $this->assertSame(1, $campos->query('//*[@data-carreras-seccion]//details[@open]')->length);
        $this->assertSame([$this->carrera->id], $seccion->fresh()->carreras()->pluck('carreras.id')->all());
    }

    public function test_filtros_por_carrera_periodo_y_literal(): void
    {
        $this->seccion(['codigo' => '100%']); $this->seccion(['codigo' => '12']);
        $this->get($this->ruta('index', ['q' => '100%']))->assertOk()->assertSee('100%')->assertDontSee('Sección 12');
        $this->get($this->ruta('index', ['carrera_id' => $this->carrera->id]))->assertOk()->assertSee('Backend');
        $this->get($this->ruta('index', ['page' => 999]))->assertRedirect();
    }

    public function test_permiso_de_administracion_y_generacion_se_verifican_por_separado(): void
    {
        $s = $this->seccion(); $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertRedirect();
        $this->usuario->revokePermissionTo('administrar usuarios');
        $this->get($this->ruta('index'))->assertRedirect(); $this->get($this->ruta('show', $s))->assertRedirect();
        $this->post($this->ruta('store'), $this->datosSeccion(['codigo' => '12']))->assertRedirect();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion()))->assertRedirect();
        auth()->logout(); $this->get($this->ruta('index'))->assertRedirect(route('login'));
    }

    public function test_formularios_con_ceros_como_texto_permiten_matricula_vacia_y_desactivacion(): void
    {
        $datos = $this->datosSeccion(['inscritos' => 0, 'cupos' => 0]);
        array_walk_recursive($datos, function (&$valor) { $valor = (string) $valor; });
        $this->post($this->ruta('store'), $datos)->assertSessionHasNoErrors();
        $s = Models\Seccion::sole(); $a = $s->actividades()->firstOrFail(); $g = $a->grupos()->firstOrFail();
        $this->assertSame(0, $g->inscritos);
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $this->datosGrupo($g, ['estado_activo' => '0', 'inscritos' => '0', 'cupos' => '0']))->assertSessionHasNoErrors();
        $this->put($this->ruta('actividad.update', ['seccion' => $s->id, 'id' => $a->id]), $this->datosActividad($a, ['estado' => '0']))->assertSessionHasNoErrors();
        $this->put($this->ruta('update', $s), $this->revision($s, ['estado_activo' => '0'] + $datos))->assertSessionHasNoErrors();
        $this->assertFalse($s->fresh()->estado_activo);
    }

    public function test_distribuir_grupos_recalcula_carga_pendiente_sin_crear_horarios(): void
    {
        $s = $this->seccion(); $a = $s->actividades()->where('tipo', 'Laboratorio')->firstOrFail(); $g = $a->grupos()->sole();
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $this->datosGrupo($g, ['cupos' => 20, 'inscritos' => 16]))->assertSessionHasNoErrors();
        $this->post($this->ruta('grupo.store', ['seccion' => $s->id, 'actividad' => $a->id]), ['codigo' => '2', 'numero' => 2, 'cupos' => 20, 'inscritos' => 16, 'estado_activo' => 1])->assertSessionHasNoErrors();
        $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1])->assertSessionHasNoErrors();
        $carga = app(RevisionCargaProgramacion::class)->revisar(Models\VersionPlanificacion::sole(), collect());
        $this->assertSame(8, $carga['requeridos']); $this->assertSame(8, $carga['pendientes']); $this->assertDatabaseCount('horarios', 0);
    }

    public function test_fallo_auditoria_revierte_inicio_de_borrador_sin_afectar_fuentes(): void
    {
        $this->seccion(); $this->withoutExceptionHandling();
        Models\VersionPlanificacion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->ruta('iniciar'), ['periodo_id' => $this->periodo->id, 'confirmar' => 1]); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\VersionPlanificacion::flushEventListeners(); }
        $this->assertDatabaseCount('versiones_planificacion', 0); $this->assertDatabaseCount('secciones', 1); $this->assertDatabaseCount('grupos_seccion', 2);
    }

    public function test_catalogo_de_niveles_se_respeta_en_altas_y_conserva_nivel_historico(): void
    {
        Models\NivelCarrera::create(['carrera_id' => $this->carrera->id, 'numero' => 1, 'nombre' => 'Primero', 'estado' => true]);
        $this->post($this->ruta('store'), $this->datosSeccion())->assertSessionHasErrors('semestre');
        $nivel = Models\NivelCarrera::create(['carrera_id' => $this->carrera->id, 'numero' => 2, 'nombre' => 'Segundo', 'estado' => true]);
        $s = $this->seccion(); $nivel->update(['estado' => false]);
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['semestre' => null])))->assertSessionHasErrors('semestre');
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['nombre' => 'Conservada'])))->assertSessionHasNoErrors();
        $this->post($this->ruta('store'), $this->datosSeccion(['codigo' => '12']))->assertSessionHasErrors('semestre');
    }

    public function test_matricula_de_seccion_y_grupo_mantiene_coherencia(): void
    {
        $s = $this->seccion(); $a = $s->actividades()->firstOrFail(); $g = $a->grupos()->sole();
        $this->put($this->ruta('update', $s), $this->revision($s, $this->datosSeccion(['inscritos' => 20])))->assertSessionHasErrors('inscritos');
        $this->put($this->ruta('grupo.update', ['seccion' => $s->id, 'actividad' => $a->id, 'id' => $g->id]), $this->datosGrupo($g, ['inscritos' => 35]))->assertSessionHasErrors('inscritos');
        $this->assertSame(32, $g->fresh()->inscritos);
    }

    public function test_redireccion_por_cambio_concurrente_no_renueva_firma_de_datos_anteriores(): void
    {
        $s = $this->seccion(); $datos = $this->revision($s, $this->datosSeccion(['nombre' => 'Cambio antiguo']));
        $s->update(['nombre' => 'Cambio reciente']); $url = $this->ruta('edit', $s);
        $this->from($url)->put($this->ruta('update', $s), $datos)->assertRedirect($url)->assertSessionHasErrors('revision');
        $this->get($url)->assertOk()->assertSee('value="'.$datos['revision'].'"', false);
        $this->assertSame('Cambio reciente', $s->fresh()->nombre);
    }

    public function test_fallo_auditoria_revierte_seccion_actividades_grupos_y_relaciones(): void
    {
        $this->withoutExceptionHandling();
        Models\GrupoSeccion::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->ruta('store'), $this->datosSeccion()); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\GrupoSeccion::flushEventListeners(); }
        foreach (['secciones', 'actividades_seccion', 'grupos_seccion', 'secciones_carreras'] as $tabla) $this->assertDatabaseCount($tabla, 0);
    }
}
