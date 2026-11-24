<?php

namespace Tests\Feature;

use App\Models\{Universidad, User};
use App\Services\InstalacionInicial;
use Illuminate\Support\Facades\{Artisan, DB, Hash, Route};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\{Permission, Role};
use Tests\IsolatedDatabaseTestCase;

class InstalacionInicialTest extends IsolatedDatabaseTestCase
{
    private function datos(array $cambios = []): array { return $cambios + ['name' => '  Coordinación   inicial ', 'rut' => '12345678', 'email' => 'COORDINACION@example.test', 'password' => 'ClaveFicticia12345', 'password_confirmation' => 'ClaveFicticia12345']; }
    private function crear(array $datos = []): User { return app(InstalacionInicial::class)->crear($this->datos($datos)); }

    public function test_crea_primer_acceso_real_con_permisos_y_clave_cifrada_sin_filtrarla_en_auditoria(): void
    {
        $u = $this->crear();
        $this->assertSame('Coordinación inicial', $u->name); $this->assertSame('coordinacion@example.test', $u->email);
        $this->assertTrue(Hash::check($this->datos()['password'], $u->password)); $this->assertTrue($u->hasRole('Administrador'));
        $permisosRutas = collect(Route::getRoutes())->flatMap(fn ($r) => $r->gatherMiddleware())->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission.redirect:'))->map(fn ($m) => substr($m, strlen('permission.redirect:')))->unique();
        foreach ($permisosRutas as $p) $this->assertTrue($u->can($p), $p);
        $auditoria = DB::table('historial_acciones')->sole();
        $this->assertStringNotContainsString($this->datos()['password'], $auditoria->datos);
        $this->assertStringNotContainsString('password', $auditoria->datos);
        $this->post(route('login.procesar'), ['rut' => $u->rut, 'password' => $this->datos()['password']])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($u);
    }
    public function test_instalacion_con_cualquier_usuario_no_se_puede_reinicializar(): void
    {
        $u = User::create(['name' => 'Cuenta existente', 'rut' => '98765432', 'email' => 'anterior@example.test', 'password' => 'clave-anterior']);
        $antes = $u->fresh()->getAttributes();
        try { $this->crear(); $this->fail('Debe conservar las cuentas existentes.'); } catch (ValidationException $e) { $this->assertArrayHasKey('instalacion', $e->errors()); }
        $this->assertSame($antes, $u->fresh()->getAttributes()); $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('roles', 0);
    }
    public function test_doble_ejecucion_conserva_primera_cuenta_y_una_sola_auditoria(): void
    {
        $u = $this->crear();
        try { $this->crear(['email' => 'otro@example.test']); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('users', 1); }
        $this->assertDatabaseCount('historial_acciones', 1); $this->assertSame($u->email, User::sole()->email);
    }
    public function test_valida_identidad_clave_y_confirmacion_sin_crear_permisos(): void
    {
        foreach ([['rut' => '12.345.678-9'], ['email' => 'incorrecto'], ['password' => 'corta'], ['password_confirmation' => 'distinta'], ['name' => '   '], ['rut' => ['no-es-texto']]] as $datos) {
            try { $this->crear($datos); $this->fail('Debe rechazar datos inválidos.'); } catch (ValidationException) { $this->assertDatabaseCount('users', 0); }
            $this->assertDatabaseCount('roles', 0); $this->assertDatabaseCount('permissions', 0);
        }
    }
    public function test_sin_institucion_activa_no_inicializa(): void
    {
        Universidad::query()->update(['estado' => false]);
        $this->expectException(ValidationException::class); $this->crear();
    }
    public function test_fallo_de_auditoria_revierte_cuenta_rol_y_permisos(): void
    {
        DB::unprepared("CREATE TRIGGER falla_inicio BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        try { $this->crear(); $this->fail(); } catch (\Illuminate\Database\QueryException) { $this->assertDatabaseCount('users', 0); }
        $this->assertDatabaseCount('roles', 0); $this->assertDatabaseCount('permissions', 0); $this->assertDatabaseCount('model_has_roles', 0);
    }
    public function test_no_quita_permisos_preparados_previamente_del_rol_administrador(): void
    {
        $p = Permission::findOrCreate('permiso institucional adicional', 'web'); Role::findOrCreate('Administrador', 'web')->givePermissionTo($p);
        $this->assertTrue($this->crear()->can('permiso institucional adicional'));
    }
    public function test_comando_interactivo_pide_datos_oculta_clave_y_confirma(): void
    {
        $this->artisan('prosec:inicializar')->expectsQuestion('Nombre del administrador', 'Coordinación inicial')
            ->expectsQuestion('RUT de acceso, sin puntos ni dígito verificador', '12345678')->expectsQuestion('Correo institucional', 'coordinacion@example.test')
            ->expectsQuestion('Contraseña', $this->datos()['password'])->expectsQuestion('Repetir contraseña', $this->datos()['password'])
            ->expectsConfirmation('¿Crear la primera cuenta con rol Administrador en la base configurada?', 'yes')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
    }
    public function test_cancelar_el_comando_no_guarda_datos(): void
    {
        $this->artisan('prosec:inicializar')->expectsQuestion('Nombre del administrador', 'Coordinación inicial')
            ->expectsQuestion('RUT de acceso, sin puntos ni dígito verificador', '12345678')->expectsQuestion('Correo institucional', 'coordinacion@example.test')
            ->expectsQuestion('Contraseña', $this->datos()['password'])->expectsQuestion('Repetir contraseña', $this->datos()['password'])
            ->expectsConfirmation('¿Crear la primera cuenta con rol Administrador en la base configurada?', 'no')->assertSuccessful();
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('permissions', 0);
    }
    public function test_no_admite_alta_no_interactiva_ni_clave_como_opcion(): void
    {
        $this->assertSame(1, Artisan::call('prosec:inicializar', ['--no-interaction' => true]));
        $this->assertDatabaseCount('users', 0);
    }
    public function test_seeder_no_crea_usuarios_de_ejemplo_ni_cambia_cuentas(): void
    {
        $this->seed(); $this->assertDatabaseCount('users', 0);
        $u = $this->crear(); $antes = $u->fresh()->getAttributes(); $this->seed();
        $this->assertDatabaseCount('users', 1); $this->assertSame($antes, $u->fresh()->getAttributes());
    }
}
