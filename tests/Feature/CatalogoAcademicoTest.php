<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class CatalogoAcademicoTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\Carrera $carrera;
    private int $universidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = (int) DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'academicos@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('administrar usuarios', 'web'));
        $this->actingAs($this->usuario);
        $sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'nombre' => 'Informática', 'codigo' => 'INF', 'estado' => true]);
    }

    private function ruta(string $accion, string $tipo, ?int $id = null): string
    {
        return route('administracion.academicos.'.$accion, array_filter(['tipo' => $tipo, 'id' => $id]));
    }
    private function datos(string $tipo, array $cambios = []): array
    {
        return $cambios + match ($tipo) {
            'facultades' => ['universidad_id' => $this->universidad, 'nombre' => 'Instituto Tecnológico', 'codigo' => 'IT', 'tipo' => 'Instituto', 'estado' => 1],
            'planes' => ['carrera_id' => $this->carrera->id, 'nombre' => 'Plan Informática', 'codigo' => 'INF-2026', 'version' => '2026', 'anio_inicio' => 2026, 'estado' => 1],
            'niveles' => ['carrera_id' => $this->carrera->id, 'nombre' => 'Segundo semestre', 'numero' => 2, 'estado' => 1],
        };
    }
    private function seccion(int $semestre, bool $vigente): Models\Seccion
    {
        $p = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => $vigente]);
        $a = Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'BACK', 'nombre' => 'Backend']);
        $s = Models\Seccion::create(['asignatura_id' => $a->id, 'periodo_academico_id' => $p->id, 'codigo' => '11', 'semestre' => $semestre, 'estado_activo' => true]);
        $s->carreras()->attach($this->carrera->id);
        return $s;
    }

    public function test_crea_edita_y_consulta_los_tres_catalogos_con_historial(): void
    {
        foreach (['facultades' => Models\FacultadInstituto::class, 'planes' => Models\PlanEstudio::class, 'niveles' => Models\NivelCarrera::class] as $tipo => $modelo) {
            $this->get($this->ruta('index', $tipo))->assertOk();
            $this->get($this->ruta('create', $tipo))->assertOk()->assertSee('Los campos con * son obligatorios.');
            $this->post($this->ruta('store', $tipo), $this->datos($tipo))->assertSessionHasNoErrors();
            $registro = $modelo::sole();
            $this->get($this->ruta('edit', $tipo, $registro->id))->assertOk()->assertSee('Guardar cambios');
            $this->put($this->ruta('update', $tipo, $registro->id), $this->datos($tipo, ['nombre' => '  Nombre   actualizado  ']))->assertSessionHasNoErrors();
            $this->assertSame('Nombre actualizado', $registro->fresh()->nombre);
            $this->get($this->ruta('index', $tipo))->assertOk()->assertSee('Nombre actualizado');
        }
        $this->assertDatabaseCount('historial_acciones', 6);
    }

    public function test_facultad_se_integra_en_la_edicion_de_carrera_y_protege_carreras_activas(): void
    {
        $f = Models\FacultadInstituto::create($this->datos('facultades'));
        $this->get(route('administracion.estructura.edit', ['tipo' => 'carreras', 'id' => $this->carrera->id]))->assertOk()->assertSee('Instituto Tecnológico');
        $this->carrera->update(['facultad_instituto_id' => $f->id]);
        $this->put($this->ruta('update', 'facultades', $f->id), $this->datos('facultades', ['estado' => 0]))->assertSessionHasErrors('estado');
        $this->carrera->update(['estado' => false]);
        $this->put($this->ruta('update', 'facultades', $f->id), $this->datos('facultades', ['estado' => 0]))->assertSessionHasNoErrors();
        $this->assertSame($f->id, $this->carrera->fresh()->facultad_instituto_id);
    }

    public function test_codigo_y_nombre_facultad_no_se_duplican_aunque_este_inactiva(): void
    {
        Models\FacultadInstituto::create($this->datos('facultades', ['estado' => 0]));
        $this->post($this->ruta('store', 'facultades'), $this->datos('facultades', ['nombre' => 'Otro', 'codigo' => ' it ']))->assertSessionHasErrors('codigo');
        $this->post($this->ruta('store', 'facultades'), $this->datos('facultades', ['nombre' => ' instituto tecnológico ', 'codigo' => 'OTRO']))->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('facultades_institutos', 1);
    }

    public function test_versiones_de_plan_pueden_compartir_nombre_sin_duplicar_codigo_o_version(): void
    {
        Models\PlanEstudio::create($this->datos('planes', ['estado' => 0]));
        $this->post($this->ruta('store', 'planes'), $this->datos('planes', ['codigo' => 'NUEVO']))->assertSessionHasErrors('nombre');
        $this->post($this->ruta('store', 'planes'), $this->datos('planes', ['nombre' => 'Otro', 'codigo' => 'inf-2026']))->assertSessionHasErrors('codigo');
        $this->post($this->ruta('store', 'planes'), $this->datos('planes', ['codigo' => 'INF-2027', 'version' => '2027']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('planes_estudio', 2);
    }

    public function test_numeros_de_nivel_son_unicos_por_carrera_e_incluyen_inactivos(): void
    {
        Models\NivelCarrera::create($this->datos('niveles', ['estado' => 0]));
        $this->post($this->ruta('store', 'niveles'), $this->datos('niveles', ['nombre' => 'Otro nombre']))->assertSessionHasErrors('numero');
        $otra = $this->carrera->replicate(); $otra->nombre = 'Enfermería'; $otra->save();
        $this->post($this->ruta('store', 'niveles'), $this->datos('niveles', ['carrera_id' => $otra->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('niveles_carrera', 2);
    }

    public function test_nivel_con_historial_no_cambia_numero_y_actividad_vigente_impide_desactivar(): void
    {
        $n = Models\NivelCarrera::create($this->datos('niveles'));
        $s = $this->seccion(2, true);
        $this->put($this->ruta('update', 'niveles', $n->id), $this->datos('niveles', ['numero' => 3]))->assertSessionHasErrors('numero');
        $this->put($this->ruta('update', 'niveles', $n->id), $this->datos('niveles', ['estado' => 0]))->assertSessionHasErrors('estado');
        $s->periodoAcademico->update(['estado' => false]);
        $this->put($this->ruta('update', 'niveles', $n->id), $this->datos('niveles', ['numero' => 3]))->assertSessionHasErrors('numero');
        $this->put($this->ruta('update', 'niveles', $n->id), $this->datos('niveles', ['estado' => 0, 'nombre' => 'Nivel segundo']))->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $s->fresh()->semestre);
        $this->assertDatabaseCount('secciones_carreras', 1);
    }

    public function test_nivel_sin_secciones_admite_corregir_numero_y_no_bloquea_otros_semestres(): void
    {
        $n = Models\NivelCarrera::create($this->datos('niveles')); $this->seccion(1, true);
        $this->put($this->ruta('update', 'niveles', $n->id), $this->datos('niveles', ['numero' => 3, 'estado' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(3, (int) $n->fresh()->numero);
    }

    public function test_no_permite_trasladar_registros_a_otra_institucion_o_carrera(): void
    {
        $otra = $this->carrera->replicate(); $otra->nombre = 'Otra carrera'; $otra->save();
        $u = Models\Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTR', 'estado' => true]);
        foreach (['facultades' => Models\FacultadInstituto::class, 'planes' => Models\PlanEstudio::class, 'niveles' => Models\NivelCarrera::class] as $tipo => $modelo) {
            $r = $modelo::create($this->datos($tipo)); $campo = $tipo === 'facultades' ? 'universidad_id' : 'carrera_id';
            $this->put($this->ruta('update', $tipo, $r->id), $this->datos($tipo, [$campo => $tipo === 'facultades' ? $u->id : $otra->id]))->assertSessionHasErrors($campo);
        }
    }

    public function test_padres_inactivos_no_admiten_nuevos_registros_y_permiten_conservar_los_previos(): void
    {
        $p = Models\PlanEstudio::create($this->datos('planes', ['estado' => 0]));
        $this->carrera->update(['estado' => false]);
        $this->post($this->ruta('store', 'niveles'), $this->datos('niveles'))->assertSessionHasErrors('carrera_id');
        $this->get($this->ruta('edit', 'planes', $p->id))->assertOk()->assertSee('Informática');
        $this->put($this->ruta('update', 'planes', $p->id), $this->datos('planes', ['estado' => 0, 'nombre' => 'Conservado']))->assertSessionHasNoErrors();
        $this->put($this->ruta('update', 'planes', $p->id), $this->datos('planes', ['nombre' => 'Conservado']))->assertSessionHasErrors('estado');
        Models\Universidad::findOrFail($this->universidad)->update(['estado' => false]);
        $this->post($this->ruta('store', 'facultades'), $this->datos('facultades'))->assertSessionHasErrors('universidad_id');
    }

    public function test_desactivar_carrera_exige_revisar_planes_y_niveles_activos(): void
    {
        $url = route('administracion.estructura.update', ['tipo' => 'carreras', 'id' => $this->carrera->id]);
        $datos = $this->carrera->only(['universidad_id', 'sede_id', 'nombre', 'codigo']) + ['estado' => 0];
        $p = Models\PlanEstudio::create($this->datos('planes'));
        $this->put($url, $datos)->assertSessionHasErrors('estado'); $p->update(['estado' => false]);
        $n = Models\NivelCarrera::create($this->datos('niveles'));
        $this->put($url, $datos)->assertSessionHasErrors('estado'); $n->update(['estado' => false]);
        $this->put($url, $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('planes_estudio', 1); $this->assertDatabaseCount('niveles_carrera', 1);
    }

    public function test_filtros_y_busqueda_literal_no_mezclan_carreras(): void
    {
        $p = Models\PlanEstudio::create($this->datos('planes', ['nombre' => 'Plan 100%', 'estado' => 0]));
        $otra = $this->carrera->replicate(); $otra->nombre = 'Otra'; $otra->save();
        Models\PlanEstudio::create($this->datos('planes', ['nombre' => 'Plan ajeno', 'carrera_id' => $otra->id]));
        $this->get($this->ruta('index', 'planes').'?estado=todos&q=100%25')->assertOk()->assertSee('Plan 100%')->assertDontSee('Plan ajeno');
        $this->get($this->ruta('index', 'planes').'?estado=todos&carrera_id='.$this->carrera->id)->assertOk()->assertSee('Plan 100%')->assertDontSee('Plan ajeno');
        $this->get($this->ruta('index', 'planes').'?page=999')->assertRedirect();
        $this->get($this->ruta('index', 'planes').'?estado=incorrecto')->assertSessionHasErrors('estado');
    }

    public function test_validacion_de_limites_y_entradas_malformadas_recupera_formulario(): void
    {
        $this->post($this->ruta('store', 'planes'), $this->datos('planes', ['anio_inicio' => 65536]))->assertSessionHasErrors('anio_inicio');
        foreach ([0, 256] as $numero) $this->post($this->ruta('store', 'niveles'), $this->datos('niveles', ['numero' => $numero]))->assertSessionHasErrors('numero');
        foreach (['facultades', 'planes', 'niveles'] as $tipo) {
            $url = $this->ruta('create', $tipo);
            $this->from($url)->post($this->ruta('store', $tipo), $this->datos($tipo, ['nombre' => ['inválido'], 'estado' => [1]]))->assertRedirect($url)->assertSessionHasErrors(['nombre', 'estado']);
            $this->get($url)->assertOk();
        }
    }

    public function test_sin_cambios_no_duplica_auditoria_y_conserva_duplicados_heredados(): void
    {
        $p = Models\PlanEstudio::create($this->datos('planes'));
        Models\PlanEstudio::create($this->datos('planes'));
        $this->put($this->ruta('update', 'planes', $p->id), $this->datos('planes'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->put($this->ruta('update', 'planes', $p->id), $this->datos('planes', ['anio_inicio' => 2025]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    public function test_acceso_exige_sesion_y_permiso_incluyendo_escrituras(): void
    {
        $this->usuario->revokePermissionTo('administrar usuarios');
        foreach (['facultades', 'planes', 'niveles'] as $tipo) {
            $this->get($this->ruta('index', $tipo))->assertRedirect();
            $this->get($this->ruta('create', $tipo))->assertRedirect();
            $this->post($this->ruta('store', $tipo), $this->datos($tipo))->assertRedirect();
            $this->put($this->ruta('update', $tipo, 1), $this->datos($tipo))->assertRedirect();
        }
        auth()->logout(); $this->get($this->ruta('index', 'planes'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_fallo_de_auditoria_revierte_plan(): void
    {
        $this->withoutExceptionHandling();
        Models\PlanEstudio::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->ruta('store', 'planes'), $this->datos('planes')); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\PlanEstudio::flushEventListeners(); }
        $this->assertDatabaseCount('planes_estudio', 0);
    }
}
