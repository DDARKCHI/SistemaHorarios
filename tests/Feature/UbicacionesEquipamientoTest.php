<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class UbicacionesEquipamientoTest extends IsolatedDatabaseTestCase
{
    private Models\Equipamiento $equipo;
    private Models\Sala $sala;
    private Models\LaboratorioMovil $movil;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $usuario = Models\User::create(['name' => 'Inventario', 'rut' => '77777777', 'email' => 'ubicaciones@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($usuario);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Sede']);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-101']);
        $this->movil = Models\LaboratorioMovil::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'codigo' => 'MOV-1', 'nombre' => 'Móvil']);
        $this->equipo = Models\Equipamiento::create(['universidad_id' => $uni, 'nombre' => 'Proyector', 'tipo' => 'Audiovisual', 'cantidad' => 5]);
    }

    public function test_distribuye_stock_en_salas_y_moviles_sin_cambiar_el_inventario_total(): void
    {
        $this->post($this->url('store'), ['espacio' => 'sala:'.$this->sala->id, 'cantidad' => 2])->assertRedirect($this->url());
        $this->post($this->url('store'), ['espacio' => 'movil:'.$this->movil->id, 'cantidad' => 3])->assertRedirect($this->url());
        $this->assertSame(5, $this->equipo->fresh()->cantidad);
        $this->assertSame(5, (int) $this->equipo->ubicaciones()->sum('cantidad'));
        $this->get($this->url())->assertOk()->assertSee('A-101')->assertSee('MOV-1')->assertSee('Todas las unidades están ubicadas');
        $this->assertDatabaseCount('historial_acciones', 2);
        $this->get(route('recursos.index'))->assertOk()->assertSee($this->url(), false);
    }

    public function test_no_admite_sobreasignar_ni_duplicar_un_espacio(): void
    {
        $u = $this->ubicar(4);
        $this->post($this->url('store'), ['espacio' => 'movil:'.$this->movil->id, 'cantidad' => 2])->assertSessionHasErrors('cantidad');
        $this->post($this->url('store'), ['espacio' => 'sala:'.$this->sala->id, 'cantidad' => 1])->assertSessionHasErrors('espacio');
        $this->put($this->url('update', $u), ['cantidad' => 6])->assertSessionHasErrors('cantidad');
        $this->assertSame(4, $u->fresh()->cantidad);
        $this->assertDatabaseCount('equipamientos_espacios', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_modifica_y_retira_ubicaciones_con_historial_y_sin_eliminar_el_equipo(): void
    {
        $u = $this->ubicar(2);
        $this->get($this->url().'?ubicacion='.$u->id)->assertOk()->assertSee('Modificar cantidad ubicada');
        $this->put($this->url('update', $u), ['cantidad' => 3])->assertRedirect();
        $this->put($this->url('update', $u), ['cantidad' => 3])->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 1);
        $this->delete($this->url('destroy', $u))->assertRedirect();
        $this->assertDatabaseCount('equipamientos_espacios', 0);
        $this->assertDatabaseCount('equipamientos', 1);
        $this->assertDatabaseCount('historial_acciones', 2);
        $this->assertSame(5, $this->equipo->fresh()->cantidad);
    }

    #[DataProvider('cantidadesInvalidas')]
    public function test_rechaza_cantidades_y_destinos_invalidos(string $campo, mixed $valor): void
    {
        $this->post($this->url('store'), array_replace(['espacio' => 'sala:'.$this->sala->id, 'cantidad' => 1], [$campo => $valor]))->assertSessionHasErrors($campo);
        $this->assertDatabaseCount('equipamientos_espacios', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function cantidadesInvalidas(): array
    {
        return [['cantidad', 0], ['cantidad', -1], ['cantidad', 1.5], ['cantidad', 65536], ['espacio', 'equipo:1'], ['espacio', 'sala:999999'], ['espacio', ['sala:1']]];
    }

    public function test_no_ubica_en_otra_institucion_ni_en_espacios_inactivos(): void
    {
        $otra = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTRA']);
        $this->sala->update(['universidad_id' => $otra->id]);
        $this->post($this->url('store'), ['espacio' => 'sala:'.$this->sala->id, 'cantidad' => 1])->assertSessionHasErrors('espacio');
        $this->movil->update(['disponible' => false]);
        $this->post($this->url('store'), ['espacio' => 'movil:'.$this->movil->id, 'cantidad' => 1])->assertSessionHasErrors('espacio');
        $this->assertDatabaseCount('equipamientos_espacios', 0);
    }

    public function test_las_ubicaciones_inactivas_conservan_stock_y_permiten_reducir_o_retirar(): void
    {
        $u = $this->ubicar(3); $this->sala->update(['estado' => false]); $this->equipo->update(['disponible' => false]);
        $this->put($this->url('update', $u), ['cantidad' => 4])->assertSessionHasErrors('espacio');
        $this->put($this->url('update', $u), ['cantidad' => 2])->assertRedirect();
        $this->assertSame(2, $u->fresh()->cantidad);
        $this->get($this->url())->assertOk()->assertSee('sus unidades siguen ubicadas aquí');
        $this->delete($this->url('destroy', $u))->assertRedirect();
    }

    public function test_no_cambia_la_ubicacion_por_el_formulario_de_cantidad_ni_accede_a_otro_equipo(): void
    {
        $u = $this->ubicar(2);
        $this->put($this->url('update', $u), ['cantidad' => 2, 'espacio' => 'movil:'.$this->movil->id])->assertSessionHasErrors('espacio');
        $otro = $this->equipo->replicate(); $otro->nombre = 'Otro equipo'; $otro->save();
        $ajena = Models\EquipamientoEspacio::create(['equipamiento_id' => $otro->id, 'sala_id' => $this->sala->id, 'cantidad' => 1]);
        $this->get($this->url().'?ubicacion='.$ajena->id)->assertNotFound();
        $this->put($this->url('update', $ajena), ['cantidad' => 1])->assertNotFound();
        $this->delete($this->url('destroy', $ajena))->assertNotFound();
        $this->assertDatabaseCount('equipamientos_espacios', 2);
    }

    public function test_no_reduce_el_inventario_por_debajo_de_lo_ubicado_ni_cambia_su_institucion(): void
    {
        $this->ubicar(4);
        $datos = ['universidad_id' => $this->equipo->universidad_id, 'nombre' => 'Proyector', 'tipo' => 'Audiovisual', 'cantidad' => 3, 'estado' => 1, 'disponible' => 1];
        $this->put(route('recursos.update', $this->equipo), $datos)->assertSessionHasErrors('cantidad');
        $otra = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTRA']);
        $this->put(route('recursos.update', $this->equipo), array_replace($datos, ['cantidad' => 5, 'universidad_id' => $otra->id]))->assertSessionHasErrors('universidad_id');
        $this->assertSame(5, $this->equipo->fresh()->cantidad);
        $this->assertSame($datos['universidad_id'], $this->equipo->fresh()->universidad_id);
    }

    public function test_exige_permiso_y_no_cambia_la_distribucion_al_consultarla(): void
    {
        $this->ubicar(2);
        $this->get($this->url())->assertOk(); $this->assertDatabaseCount('historial_acciones', 0);
        auth()->user()->revokePermissionTo('gestionar salas y recursos');
        $this->post($this->url('store'), ['espacio' => 'movil:'.$this->movil->id, 'cantidad' => 1])->assertRedirect();
        $this->assertDatabaseCount('equipamientos_espacios', 1);
    }

    public function test_un_error_en_el_historial_revierte_la_distribucion(): void
    {
        $this->withoutExceptionHandling();
        Models\EquipamientoEspacio::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->url('store'), ['espacio' => 'sala:'.$this->sala->id, 'cantidad' => 1]); $this->fail('El historial debe ser atómico.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        $this->assertDatabaseCount('equipamientos_espacios', 0);
    }

    private function ubicar(int $cantidad): Models\EquipamientoEspacio { return Models\EquipamientoEspacio::create(['equipamiento_id' => $this->equipo->id, 'sala_id' => $this->sala->id, 'cantidad' => $cantidad]); }
    private function url(string $accion = 'index', ?Models\EquipamientoEspacio $ubicacion = null): string
    {
        return route('recursos.ubicaciones.'.$accion, array_filter(['equipamiento' => $this->equipo->id, 'ubicacion' => $ubicacion?->id]));
    }
}
