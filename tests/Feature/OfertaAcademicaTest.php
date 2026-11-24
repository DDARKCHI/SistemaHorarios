<?php

namespace Tests\Feature;

use App\Models;
use App\Http\Controllers\OfertaAcademicaController;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class OfertaAcademicaTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private int $universidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = (int) DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'oferta@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('administrar usuarios', 'web'));
        $this->actingAs($this->usuario);
    }
    private function ruta(string $accion, string $tipo, ?int $id = null): string
    {
        return route('administracion.oferta.'.$accion, array_filter(['tipo' => $tipo, 'id' => $id]));
    }
    private function datos(string $tipo, array $cambios = []): array
    {
        return $cambios + ['universidad_id' => $this->universidad, 'estado' => 1] + ($tipo === 'periodos'
            ? ['nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-20']
            : ['codigo' => 'BACK', 'nombre' => 'Backend', 'creditos' => 5, 'es_libre_eleccion' => 0, 'ramo_terminal' => 0] + array_fill_keys(array_keys(OfertaAcademicaController::MODULOS), 0));
    }
    private function periodo(): Models\PeriodoAcademico { return Models\PeriodoAcademico::create($this->datos('periodos')); }
    private function asignatura(): Models\Asignatura { return Models\Asignatura::create($this->datos('asignaturas')); }
    private function seccion(Models\PeriodoAcademico $periodo, Models\Asignatura $ramo): Models\Seccion
    {
        return Models\Seccion::create(['asignatura_id' => $ramo->id, 'periodo_academico_id' => $periodo->id, 'codigo' => '11', 'semestre' => 2, 'estado_activo' => true]);
    }
    private function profesor(): Models\Profesor
    {
        return Models\Profesor::create(['universidad_id' => $this->universidad, 'rut' => '12345678', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'estado' => true]);
    }
    private function version(Models\PeriodoAcademico $p, string $estado = 'Borrador'): Models\VersionPlanificacion
    {
        return Models\VersionPlanificacion::create(['periodo_academico_id' => $p->id, 'nombre' => 'Semestral', 'version' => '0.1', 'tipo' => 'semestral', 'es_actual' => true, 'estado' => $estado, 'usuario_id' => $this->usuario->id]);
    }

    public function test_creacion_edicion_y_listados_conservan_identidad_y_auditoria(): void
    {
        foreach (['asignaturas' => Models\Asignatura::class, 'periodos' => Models\PeriodoAcademico::class] as $tipo => $modelo) {
            $this->get($this->ruta('create', $tipo))->assertOk()->assertSee('Los campos con * son obligatorios.');
            $this->post($this->ruta('store', $tipo), $this->datos($tipo))->assertSessionHasNoErrors();
            $registro = $modelo::sole();
            $this->get($this->ruta('edit', $tipo, $registro->id))->assertOk()->assertSee('Guardar cambios');
            $this->put($this->ruta('update', $tipo, $registro->id), $this->datos($tipo, ['nombre' => '  Nombre  actualizado ']))->assertSessionHasNoErrors();
            $this->assertSame('Nombre actualizado', $registro->fresh()->nombre);
            $this->get($this->ruta('index', $tipo))->assertOk()->assertSee('Nombre actualizado');
        }
        $this->assertDatabaseCount('historial_acciones', 4);
    }

    public function test_codigo_unico_por_institucion_incluye_inactivos_y_no_restringe_nombres(): void
    {
        $this->asignatura()->update(['estado' => false]);
        $this->post($this->ruta('store', 'asignaturas'), $this->datos('asignaturas', ['codigo' => ' back ']))->assertSessionHasErrors('codigo');
        $this->post($this->ruta('store', 'asignaturas'), $this->datos('asignaturas', ['codigo' => 'BACK2']))->assertSessionHasNoErrors();
        $otra = Models\Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTR', 'estado' => true]);
        $this->post($this->ruta('store', 'asignaturas'), $this->datos('asignaturas', ['universidad_id' => $otra->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('asignaturas', 3);
    }

    public function test_codigo_vinculado_se_conserva_y_cambio_de_modulos_no_recalcula_actividades(): void
    {
        $ramo = $this->asignatura(); $s = $this->seccion($this->periodo(), $ramo);
        $a = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3, 'cantidad_estudiantes' => 20, 'estado' => true]);
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $this->datos('asignaturas', ['codigo' => 'OTRO']))->assertSessionHasErrors('codigo');
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $this->datos('asignaturas', ['horas_catedra' => 8, 'nombre' => 'Backend avanzado']))->assertSessionHasNoErrors();
        $this->assertSame(8, (int) $ramo->fresh()->horas_catedra);
        $this->assertSame(3, (int) $a->fresh()->cantidad_horas);
        $this->assertSame($ramo->id, $s->fresh()->asignatura_id);
    }

    public function test_prioridad_por_ramo_tambien_protege_su_codigo(): void
    {
        $ramo = $this->asignatura();
        Models\PrioridadRecurso::create(['nombre' => 'Computación', 'nivel' => 1, 'asignatura_id' => $ramo->id, 'tipo_recurso' => 'laboratorio', 'clave_recurso' => 'laboratorio:todos', 'estado' => true]);
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $this->datos('asignaturas', ['codigo' => 'OTRO']))->assertSessionHasErrors('codigo');
    }

    public function test_ramo_sin_referencias_admite_corregir_codigo_y_no_pierde_flags(): void
    {
        $ramo = $this->asignatura();
        $datos = $this->datos('asignaturas', ['codigo' => ' BACK-2 ', 'es_libre_eleccion' => 1, 'ramo_terminal' => 1, 'modalidad' => ' Presencial ']);
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $datos)->assertSessionHasNoErrors();
        $this->assertSame('BACK-2', $ramo->fresh()->codigo); $this->assertTrue($ramo->fresh()->ramo_terminal); $this->assertTrue($ramo->fresh()->es_libre_eleccion);
        $datos['es_libre_eleccion'] = $datos['ramo_terminal'] = 0;
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $datos)->assertSessionHasNoErrors();
        $this->assertFalse($ramo->fresh()->ramo_terminal);
    }

    public function test_no_desactiva_ramo_con_secciones_vigentes_y_conserva_historicas(): void
    {
        $ramo = $this->asignatura(); $p = $this->periodo(); $this->seccion($p, $ramo);
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $this->datos('asignaturas', ['estado' => 0]))->assertSessionHasErrors('estado');
        $p->update(['estado' => false]);
        $this->put($this->ruta('update', 'asignaturas', $ramo->id), $this->datos('asignaturas', ['estado' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('secciones', 1);
    }

    public function test_limites_numericos_y_fechas_invalidas_no_se_guardan(): void
    {
        $this->post($this->ruta('store', 'asignaturas'), $this->datos('asignaturas', ['horas_clinica' => 256, 'creditos' => -1]))->assertSessionHasErrors(['horas_clinica', 'creditos']);
        $this->post($this->ruta('store', 'asignaturas'), $this->datos('asignaturas', ['horas_laboratorio' => 1.5]))->assertSessionHasErrors('horas_laboratorio');
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['fecha_fin' => '2026-07-31']))->assertSessionHasErrors('fecha_fin');
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['fecha_inicio' => '2026-02-30']))->assertSessionHasErrors('fecha_inicio');
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['numero_semestre' => 3]))->assertSessionHasErrors('numero_semestre');
        $this->assertDatabaseCount('asignaturas', 0); $this->assertDatabaseCount('periodos_academicos', 0);
    }

    public function test_periodo_controla_nombre_y_anio_semestre_duplicados(): void
    {
        $this->periodo()->update(['estado' => false]);
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['nombre' => 'Otro']))->assertSessionHasErrors('numero_semestre');
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['anio' => 2027]))->assertSessionHasErrors('nombre');
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['anio' => 2027, 'nombre' => '2027-2']))->assertSessionHasNoErrors();
    }

    public function test_periodo_usado_no_cambia_anio_semestre_pero_si_nombre_y_fechas(): void
    {
        $p = $this->periodo(); $this->seccion($p, $this->asignatura());
        $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos', ['anio' => 2027]))->assertSessionHasErrors('anio');
        $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos', ['numero_semestre' => 1]))->assertSessionHasErrors('anio');
        $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos', ['nombre' => 'Segundo semestre 2026', 'fecha_fin' => '2026-12-30']))->assertSessionHasNoErrors();
        $this->assertSame('2026-12-30', $p->fresh()->fecha_fin->format('Y-m-d'));
    }

    public function test_periodo_sin_uso_admite_corregir_anio_y_no_desactiva_otros_periodos(): void
    {
        $p = $this->periodo();
        $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos', ['anio' => 2027]))->assertSessionHasNoErrors();
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['nombre' => 'Otro período']))->assertSessionHasNoErrors();
        $this->assertSame(2, Models\PeriodoAcademico::where('estado', true)->count());
    }

    public function test_archivo_exige_confirmacion_y_conserva_publicacion_y_secciones(): void
    {
        $p = $this->periodo(); $this->seccion($p, $this->asignatura()); $v = $this->version($p, 'Publicado');
        $datos = $this->datos('periodos', ['estado' => 0]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasErrors('confirmar_archivo');
        $this->put($this->ruta('update', 'periodos', $p->id), ['confirmar_archivo' => 1] + $datos)->assertSessionHasNoErrors();
        $this->assertFalse((bool) $p->fresh()->estado); $this->assertSame('Publicado', $v->fresh()->estado);
        $this->assertDatabaseCount('secciones', 1); $this->assertTrue($v->fresh()->es_actual);
    }

    public function test_borrador_y_aprobacion_actuales_impiden_archivar(): void
    {
        $p = $this->periodo(); $v = $this->version($p);
        $datos = $this->datos('periodos', ['estado' => 0, 'confirmar_archivo' => 1]);
        foreach (['Borrador', 'Aprobado'] as $estado) {
            $v->update(['estado' => $estado]);
            $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasErrors('estado');
        }
        $v->update(['es_actual' => false]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_disponibilidad_pendiente_hoy_y_en_revision_impiden_archivo(): void
    {
        $p = $this->periodo();
        $s = Models\SolicitudDisponibilidad::create(['periodo_academico_id' => $p->id, 'profesor_id' => $this->profesor()->id, 'estado' => 'Pendiente', 'fecha_limite' => today()]);
        $datos = $this->datos('periodos', ['estado' => 0, 'confirmar_archivo' => 1]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasErrors('estado');
        $s->update(['estado' => 'En revisión', 'fecha_limite' => today()->subDay()]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasErrors('estado');
        $s->update(['estado' => 'Aprobada']);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('solicitudes_disponibilidad', 1);
    }

    public function test_solicitud_cambio_abierta_en_periodo_impide_archivo_hasta_vencer(): void
    {
        $p = $this->periodo(); $v = $this->version($p, 'Publicado'); $s = $this->seccion($p, $this->asignatura());
        $a = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'estado' => true]);
        $d = Models\Dia::create(['nombre' => 'Lunes', 'abreviatura' => 'LU', 'numero' => 1, 'estado' => true]);
        $b = Models\BloqueHorario::create(['numero' => 1, 'hora_inicio' => '08:10', 'hora_fin' => '09:00', 'estado' => true]);
        $h = Models\Horario::create(['actividad_seccion_id' => $a->id, 'version_planificacion_id' => $v->id, 'dia_id' => $d->id, 'bloque_horario_id' => $b->id, 'estado' => true]);
        $cambio = Models\SolicitudCambioHorario::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor()->id, 'usuario_id' => $this->usuario->id, 'motivo' => 'Cambio', 'estado_activo' => true, 'estado' => 'Respondida', 'fecha_limite' => now()->addDay()]);
        $datos = $this->datos('periodos', ['estado' => 0, 'confirmar_archivo' => 1]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasErrors('estado');
        $cambio->update(['fecha_limite' => now()->subDay()]);
        $this->put($this->ruta('update', 'periodos', $p->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('solicitudes_cambio_horario', 1);
    }

    public function test_no_cambia_institucion_y_no_crea_registros_bajo_institucion_inactiva(): void
    {
        $otra = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTR', 'estado' => true]);
        foreach (['periodos' => $this->periodo(), 'asignaturas' => $this->asignatura()] as $tipo => $registro) {
            $this->put($this->ruta('update', $tipo, $registro->id), $this->datos($tipo, ['universidad_id' => $otra->id]))->assertSessionHasErrors('universidad_id');
        }
        $otra->update(['estado' => false]);
        $this->post($this->ruta('store', 'periodos'), $this->datos('periodos', ['universidad_id' => $otra->id]))->assertSessionHasErrors('universidad_id');
    }

    public function test_busqueda_literal_y_filtros_no_mezclan_resultados(): void
    {
        $p = $this->periodo(); $p->update(['nombre' => 'Avance 100%', 'estado' => false]);
        Models\PeriodoAcademico::create($this->datos('periodos', ['anio' => 2027, 'nombre' => '2027-2']));
        $this->get($this->ruta('index', 'periodos').'?estado=todos&q=100%25')->assertOk()->assertSee('Avance 100%')->assertDontSee('2027-2');
        $this->get($this->ruta('index', 'periodos').'?estado=todos&anio=2027')->assertOk()->assertSee('2027-2')->assertDontSee('Avance 100%');
        $this->get($this->ruta('index', 'periodos').'?page=999')->assertRedirect();
    }

    public function test_campos_malformados_se_muestran_sin_romper_formulario(): void
    {
        foreach (['periodos', 'asignaturas'] as $tipo) {
            $url = $this->ruta('create', $tipo);
            $this->from($url)->post($this->ruta('store', $tipo), $this->datos($tipo, ['nombre' => ['inválido'], 'universidad_id' => [1], 'estado' => [1]]))->assertRedirect($url)->assertSessionHasErrors(['nombre', 'universidad_id', 'estado']);
            $this->get($url)->assertOk();
        }
    }

    public function test_sin_cambios_no_duplica_historial(): void
    {
        $p = $this->periodo();
        $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_permisos_protegen_lectura_y_escritura(): void
    {
        $this->usuario->revokePermissionTo('administrar usuarios');
        foreach (['periodos', 'asignaturas'] as $tipo) {
            $this->get($this->ruta('index', $tipo))->assertRedirect(); $this->get($this->ruta('create', $tipo))->assertRedirect();
            $this->post($this->ruta('store', $tipo), $this->datos($tipo))->assertRedirect();
            $this->put($this->ruta('update', $tipo, 1), $this->datos($tipo))->assertRedirect();
        }
        auth()->logout(); $this->get($this->ruta('index', 'periodos'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_fallo_auditoria_revierte_archivo(): void
    {
        $p = $this->periodo(); $this->withoutExceptionHandling();
        Models\PeriodoAcademico::updated(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->put($this->ruta('update', 'periodos', $p->id), $this->datos('periodos', ['estado' => 0, 'confirmar_archivo' => 1])); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\PeriodoAcademico::flushEventListeners(); }
        $this->assertTrue((bool) $p->fresh()->estado);
    }
}
