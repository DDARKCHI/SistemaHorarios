<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class AjustesInterfazTest extends IsolatedDatabaseTestCase
{
    private function usuario(): User {
        $u = User::create(['name'=>'Ejemplo', 'rut'=>'11222333', 'email'=>'ejemplo@example.test', 'password'=>'ClaveAnterior123']);
        foreach(['ver panel inicio','asignar profesores','administrar usuarios'] as $p) $u->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->actingAs($u); return $u;
    }
    public function test_cambia_solo_su_contrasena_y_cierra_sesion(): void {
        $u=$this->usuario();
        $this->get(route('cuenta.password.edit'))->assertOk()->assertSee('Contraseña actual');
        $this->put(route('cuenta.password.update'), ['current_password'=>'ClaveAnterior123','password'=>'ClaveNuevaSegura123','password_confirmation'=>'ClaveNuevaSegura123'])->assertRedirect(route('login'));
        $this->assertGuest(); $this->assertTrue(Hash::check('ClaveNuevaSegura123',$u->fresh()->password));
        $this->assertDatabaseHas('historial_acciones',['accion'=>'Cambiar contraseña propia']);
        $this->assertStringNotContainsString('Clave',DB::table('historial_acciones')->sole()->datos);
    }
    public function test_rechaza_clave_actual_incorrecta_sin_conservarla_en_la_sesion(): void {
        $u=$this->usuario(); $antes=$u->password;
        $this->put(route('cuenta.password.update'), ['current_password'=>'Incorrecta123','password'=>'ClaveNuevaSegura123','password_confirmation'=>'ClaveNuevaSegura123'])->assertSessionHasErrors('current_password');
        $this->assertSame($antes,$u->fresh()->password); $this->assertNull(session()->getOldInput('current_password'));
    }
    public function test_no_acepta_confirmacion_distinta_y_anonimos_no_entran(): void {
        $this->usuario();
        $this->put(route('cuenta.password.update'), ['current_password'=>'ClaveAnterior123','password'=>'ClaveNuevaSegura123','password_confirmation'=>'Otra'])->assertSessionHasErrors('password');
        auth()->logout(); $this->get(route('cuenta.password.edit'))->assertRedirect(route('login'));
    }
    public function test_menu_ordenado_y_paginacion_en_espanol(): void {
        $this->usuario();
        $html=view('components.sidebar')->render();
        $this->assertLessThan(strpos($html,'aria-label="Disponibilidad docente"'),strpos($html,'aria-label="Inicio"'));
        $this->get(route('inicio'))->assertOk()->assertSee('Cambiar contraseña');
        $this->assertSame('Mostrando',__('Showing')); $this->assertSame('resultados',__('results'));
        $this->assertSame('Anterior',__('pagination.previous'));
    }
    public function test_un_fallo_de_auditoria_revierte_el_cambio(): void {
        $u=$this->usuario(); $antes=$u->password;
        DB::unprepared("CREATE TRIGGER falla_clave BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $this->withoutExceptionHandling();
        try { $this->put(route('cuenta.password.update'), ['current_password'=>'ClaveAnterior123','password'=>'ClaveNuevaSegura123','password_confirmation'=>'ClaveNuevaSegura123']); $this->fail(); }
        catch(\Illuminate\Database\QueryException) { $this->assertSame($antes,$u->fresh()->password); }
    }
}
