<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class LaboratorioDesdeSalaTest extends IsolatedDatabaseTestCase
{
    private Models\Sala $sala;

    protected function setUp(): void
    {
        parent::setUp();
        $usuario = Models\User::create(['name' => 'Gestión de espacios', 'rut' => '16666666', 'email' => 'laboratorio-sala@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($usuario);
        $sede = Models\Sede::create(['universidad_id' => DB::table('universidades')->value('id'), 'nombre' => 'Talcahuano', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Sala']);
        $this->sala = Models\Sala::create(['universidad_id' => $sede->universidad_id, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'TH-LAB', 'nombre' => 'TH-LAB', 'capacidad' => 20, 'estado' => true, 'disponible' => true]);
    }

    public function test_el_formulario_ofrece_el_nombre_codigo_y_capacidad_de_la_sala(): void
    {
        $this->get(route('laboratorios.create', ['tipo' => 'fijo']))->assertOk()
            ->assertSee('data-nombre="TH-LAB"', false)->assertSee('data-codigo="TH-LAB"', false)
            ->assertSee('data-capacidad="20"', false)->assertSee('Usar automáticamente el nombre de la sala');
        $this->assertDatabaseCount('laboratorios', 0);
    }

    public function test_el_nombre_automatico_se_obtiene_de_la_sala_en_el_servidor(): void
    {
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->laboratorio(['usar_nombre_sala' => 1, 'nombre' => 'Nombre alterado']))
            ->assertSessionHasNoErrors();
        $lab = Models\Laboratorio::sole();
        $this->assertSame('TH-LAB', $lab->nombre);
        $this->assertSame($this->sala->id, $lab->sala_id);
        $this->assertSame(20, $lab->capacidad);
        $this->get(route('salas.edit', $this->sala))->assertOk()->assertSee('ya está registrada como laboratorio')->assertSee(route('laboratorios.edit', ['tipo' => 'fijo', 'id' => $lab->id]), false);
    }

    public function test_usa_el_codigo_cuando_la_sala_no_tiene_nombre_y_permite_un_nombre_manual(): void
    {
        $this->sala->update(['nombre' => null]);
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->laboratorio(['usar_nombre_sala' => 1, 'nombre' => null]))->assertSessionHasNoErrors();
        $lab = Models\Laboratorio::sole();
        $this->assertSame('TH-LAB', $lab->nombre);
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $lab->id]), $this->laboratorio(['usar_nombre_sala' => 0, 'nombre' => 'Laboratorio de informática']))->assertSessionHasNoErrors();
        $this->assertSame('Laboratorio de informática', $lab->fresh()->nombre);
    }

    public function test_marcar_una_sala_nueva_crea_su_laboratorio_con_datos_coherentes(): void
    {
        $this->post(route('salas.store'), $this->datosSala(['codigo' => 'TH-LAB-2', 'nombre' => 'Laboratorio de computación', 'registrar_laboratorio' => 1]))->assertSessionHasNoErrors();
        $sala = Models\Sala::where('codigo', 'TH-LAB-2')->firstOrFail();
        $lab = Models\Laboratorio::sole();
        $this->assertSame($sala->id, $lab->sala_id);
        $this->assertSame($sala->nombre, $lab->nombre);
        $this->assertSame($sala->codigo, $lab->codigo);
        $this->assertSame($sala->capacidad, $lab->capacidad);
        $this->assertDatabaseCount('historial_acciones', 2);
        $this->get(route('salas.index'))->assertOk()->assertSee('Laboratorio');
    }

    public function test_se_puede_marcar_una_sala_existente_sin_cambiar_sus_datos_y_no_se_duplica(): void
    {
        $datos = $this->datosSala(['registrar_laboratorio' => 1]);
        $this->put(route('salas.update', $this->sala), $datos)->assertSessionHasNoErrors();
        $this->put(route('salas.update', $this->sala), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laboratorios', 1);
        $this->assertDatabaseCount('historial_acciones', 1);
        $this->assertSame('TH-LAB', Models\Laboratorio::sole()->nombre);
    }

    public function test_conserva_un_laboratorio_existente_y_no_crea_otro_al_marcar_la_sala(): void
    {
        $lab = Models\Laboratorio::create($this->laboratorio(['codigo' => 'ESPECIAL', 'nombre' => 'Laboratorio especializado', 'capacidad' => 15]));
        $antes = $lab->fresh()->getAttributes();
        $this->put(route('salas.update', $this->sala), $this->datosSala(['registrar_laboratorio' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($antes, $lab->fresh()->getAttributes());
        $this->assertDatabaseCount('laboratorios', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_marca_otras_salas_ni_convierte_laboratorios_moviles(): void
    {
        $this->put(route('salas.update', $this->sala), $this->datosSala(['registrar_laboratorio' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laboratorios', 0);
        $this->post(route('laboratorios.store', ['tipo' => 'movil']), ['sede_id' => $this->sala->sede_id, 'codigo' => 'MOV-1',
            'nombre' => 'Móvil', 'usar_nombre_sala' => 1, 'capacidad' => 20, 'estado' => 1, 'disponible' => 1])->assertSessionHasErrors('usar_nombre_sala');
        $this->assertDatabaseCount('laboratorios_moviles', 0);
    }

    public function test_un_fallo_en_el_historial_revierte_la_sala_y_su_laboratorio(): void
    {
        $this->withoutExceptionHandling();
        Models\Laboratorio::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post(route('salas.store'), $this->datosSala(['codigo' => 'LAB-NUEVO', 'registrar_laboratorio' => 1])); $this->fail('La creación debe ser atómica.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        $this->assertDatabaseCount('salas', 1);
        $this->assertDatabaseCount('laboratorios', 0);
    }

    private function laboratorio(array $cambios = []): array
    {
        return array_replace(['sala_id' => $this->sala->id, 'codigo' => 'TH-LAB', 'nombre' => 'TH-LAB', 'capacidad' => 20, 'estado' => 1, 'disponible' => 1], $cambios);
    }

    private function datosSala(array $cambios = []): array
    {
        return array_replace($this->sala->only(['sede_id', 'tipo_sala_id', 'codigo', 'nombre', 'capacidad', 'edificio', 'piso', 'descripcion', 'estado', 'disponible']), $cambios);
    }
}
