<?php

namespace Tests\Feature;

use App\Models;
use App\Support\MigaDePan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class PrioridadesRecursosTest extends IsolatedDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $usuario = Models\User::create(['name' => 'Prioridades de recursos', 'rut' => '79999999', 'email' => 'prioridades@example.test', 'password' => 'prueba']);
        foreach (['gestionar salas y recursos', 'gestionar conflictos', 'asignar horarios', 'ver panel inicio'] as $permiso) $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($usuario);
    }

    public function test_el_catalogo_y_los_formularios_se_consultan_sin_crear_datos(): void
    {
        $this->get(route('prioridades.index'))->assertOk()->assertSee('No hay prioridades para esta consulta')->assertSee('Crear prioridad');
        $this->get(route('prioridades.create'))->assertOk()->assertSee('Crear prioridad')->assertSee('Estado del registro');
        $this->assertDatabaseCount('prioridades_recursos', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_crea_una_prioridad_normalizada_y_la_registra_en_el_historial(): void
    {
        $this->post(route('prioridades.store'), $this->datos(['nombre' => '  Prácticas   especializadas  ']))->assertRedirect()->assertSessionHas('success');
        $prioridad = Models\PrioridadRecurso::sole();
        $this->assertSame('Prácticas especializadas', $prioridad->nombre);
        $this->assertSame(1, $prioridad->nivel);
        $this->assertTrue($prioridad->estado);
        $log = DB::table('historial_acciones')->sole();
        $datos = json_decode($log->datos, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Crear prioridad', $log->accion);
        $this->assertNull($datos['antes']);
        $this->assertSame('Prácticas especializadas', $datos['despues']['nombre']);
    }

    public function test_edita_desactiva_y_reactiva_sin_perder_requerimientos_vinculados(): void
    {
        $prioridad = $this->prioridad();
        $actividad = $this->actividad();
        $regla = Models\RequerimientoActividad::create(['actividad_seccion_id' => $actividad->id, 'tipo_requerimiento' => 'Sala', 'prioridad_recurso_id' => $prioridad->id, 'obligatorio' => true]);
        $antes = $regla->fresh()->getAttributes();
        $this->put(route('prioridades.update', $prioridad), $this->datos(['estado' => 0, 'nivel' => 3]))->assertRedirect();
        $this->assertFalse($prioridad->fresh()->estado);
        $this->assertSame($antes, $regla->fresh()->getAttributes());
        $this->get(route('prioridades.index', ['estado' => 'inactivas']))->assertOk()->assertSee('Inactiva')->assertSee('1 requerimiento vinculado');
        $this->put(route('prioridades.update', $prioridad), $this->datos(['nivel' => 2]))->assertRedirect();
        $this->assertTrue($prioridad->fresh()->estado);
        $this->assertSame(2, $prioridad->fresh()->nivel);
        $this->assertDatabaseCount('prioridades_recursos', 1);
        $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_guardar_sin_cambios_no_duplica_el_historial(): void
    {
        $this->post(route('prioridades.store'), $this->datos())->assertRedirect();
        $prioridad = Models\PrioridadRecurso::sole();
        $this->put(route('prioridades.update', $prioridad), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos_sin_guardado_parcial(string $campo, mixed $valor): void
    {
        $this->post(route('prioridades.store'), $this->datos([$campo => $valor]))->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('prioridades_recursos', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function datosInvalidos(): array
    {
        return [['nombre', '   '], ['nombre', ['Prioridad']], ['nombre', str_repeat('x', 101)], ['descripcion', str_repeat('x', 256)],
            ['nivel', 0], ['nivel', -1], ['nivel', 256], ['nivel', 1.5], ['nivel', [1]], ['estado', 'otro']];
    }

    public function test_no_crea_nombres_duplicados_y_permite_niveles_compartidos(): void
    {
        $this->prioridad();
        $this->post(route('prioridades.store'), $this->datos(['nombre' => '  Prácticas   prioritarias ']))->assertSessionHasErrors('nombre');
        $this->post(route('prioridades.store'), $this->datos(['nombre' => 'Otra prioridad']))->assertRedirect();
        $this->assertDatabaseCount('prioridades_recursos', 2);
        $this->assertSame([1, 1], Models\PrioridadRecurso::orderBy('id')->pluck('nivel')->all());
    }

    public function test_ordena_por_nivel_y_filtra_por_estado_nombre_y_nivel(): void
    {
        $baja = $this->prioridad(['nombre' => 'A menor preferencia', 'nivel' => 3]);
        $alta = $this->prioridad(['nombre' => 'Z mayor preferencia', 'nivel' => 1]);
        $inactiva = $this->prioridad(['nombre' => 'Histórica', 'nivel' => 2, 'estado' => false]);
        $this->get(route('prioridades.index'))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->pluck('id')->all() === [$alta->id, $baja->id]);
        $this->get(route('prioridades.index', ['estado' => 'inactivas']))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->pluck('id')->all() === [$inactiva->id]);
        $this->get(route('prioridades.index', ['nivel' => 3]))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->pluck('id')->all() === [$baja->id]);
        $this->get(route('prioridades.index', ['q' => 'mayor']))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->pluck('id')->all() === [$alta->id]);
        $this->get(route('prioridades.index', ['q' => '%']))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->total() === 0);
    }

    public function test_pagina_y_valida_las_consultas_sin_perder_filtros(): void
    {
        for ($n = 1; $n <= 14; $n++) $this->prioridad(['nombre' => 'Prioridad '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'nivel' => 2]);
        $this->get(route('prioridades.index', ['nivel' => 2, 'estado' => 'todas']))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->total() === 14 && $p->count() === 12);
        $this->get(route('prioridades.index', ['nivel' => 2, 'estado' => 'todas', 'page' => 2]))->assertOk()->assertViewHas('prioridades', fn ($p) => $p->count() === 2 && str_contains($p->previousPageUrl(), 'nivel=2'));
        foreach (['q' => ['x'], 'nivel' => 0, 'estado' => 'otro', 'page' => -1] as $campo => $valor) $this->get(route('prioridades.index', [$campo => $valor]))->assertRedirect()->assertSessionHasErrors($campo);
        $this->get(route('prioridades.index', ['page' => 99]))->assertRedirect();
    }

    public function test_selecciona_la_prioridad_en_la_actividad_y_conserva_la_inactiva_actual(): void
    {
        $activa = $this->prioridad(); $inactiva = $this->prioridad(['nombre' => 'Anterior', 'estado' => false]);
        $actividad = $this->actividad();
        $this->get(route('programacion.requerimientos.create', ['actividad' => $actividad->id]))->assertOk()->assertSee('Prácticas prioritarias · Nivel 1')->assertDontSee('Anterior (inactiva)')->assertSee(route('prioridades.index'), false);
        $r = Models\RequerimientoActividad::create(['actividad_seccion_id' => $actividad->id, 'tipo_requerimiento' => 'Sala', 'prioridad_recurso_id' => $inactiva->id]);
        $this->get(route('programacion.requerimientos.edit', ['actividad' => $actividad->id, 'requerimiento' => $r->id]))->assertOk()->assertSee('Anterior (inactiva) · Nivel 1');
        $this->post(route('programacion.requerimientos.store', ['actividad' => $actividad->id]), ['tipo_requerimiento' => 'Sala', 'cantidad' => 1, 'prioridad_recurso_id' => $activa->id, 'obligatorio' => 1, 'estado' => 1])->assertRedirect();
        $this->assertSame($activa->id, Models\RequerimientoActividad::orderByDesc('id')->first()->prioridad_recurso_id);
    }

    public function test_exige_el_permiso_y_no_ofrece_acceso_en_el_menu_sin_el(): void
    {
        $prioridad = $this->prioridad();
        $this->get(route('prioridades.index'))->assertOk()->assertSee(route('prioridades.create'), false);
        auth()->user()->revokePermissionTo('gestionar salas y recursos');
        $this->get(route('prioridades.index'))->assertRedirect(route('inicio'));
        $this->post(route('prioridades.store'), $this->datos())->assertRedirect(route('inicio'));
        $this->put(route('prioridades.update', $prioridad), $this->datos(['nivel' => 2]))->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertOk()->assertDontSee(route('prioridades.index'), false);
        $this->assertSame(1, $prioridad->fresh()->nivel);
        $this->assertDatabaseCount('historial_acciones', 0);
        auth()->logout(); $this->get(route('prioridades.index'))->assertRedirect(route('login'));
    }

    public function test_las_migas_de_pan_regresan_al_catalogo_con_el_permiso_correspondiente(): void
    {
        foreach (['prioridades.create' => 'Crear prioridad', 'prioridades.edit' => 'Editar prioridad'] as $nombre => $etiqueta) {
            $request = Request::create('/prioridades/crear'); $ruta = Route::getRoutes()->getByName($nombre); $request->setRouteResolver(fn () => $ruta);
            $migas = MigaDePan::para($request, auth()->user());
            $this->assertSame(['Inicio', 'Prioridades de recursos', $etiqueta], array_column($migas, 'label'));
            $this->assertSame(route('prioridades.index'), $migas[1]['url']);
        }
    }

    public function test_los_textos_se_escapan_y_el_historial_es_atomico(): void
    {
        $prioridad = $this->prioridad(['nombre' => '<script>alert(1)</script>']);
        $this->get(route('prioridades.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withoutExceptionHandling();
        Models\PrioridadRecurso::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post(route('prioridades.store'), $this->datos()); $this->fail('El historial debe guardarse en la misma transacción.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('historial_acciones', $error->getMessage()); }
        $this->assertDatabaseCount('prioridades_recursos', 1);
        $this->assertSame($prioridad->id, Models\PrioridadRecurso::sole()->id);
    }

    private function datos(array $cambios = []): array { return array_replace(['nombre' => 'Prácticas prioritarias', 'nivel' => 1, 'descripcion' => 'Uso preferente para actividades prácticas.', 'estado' => 1], $cambios); }
    private function prioridad(array $cambios = []): Models\PrioridadRecurso { return Models\PrioridadRecurso::create($this->datos($cambios)); }
    private function actividad(): Models\ActividadSeccion
    {
        $uni = DB::table('universidades')->value('id');
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'PRIO-1', 'nombre' => 'Asignatura de prueba']);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $periodo->id, 'codigo' => '11']);
        return Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
    }
}
