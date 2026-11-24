<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GestionPerfiles;
use App\Support\{MigaDePan, PermisosSistema};
use Illuminate\Support\Facades\{DB, Hash, Route};
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;
use Tests\IsolatedDatabaseTestCase;

class PerfilesPermisosTest extends IsolatedDatabaseTestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->cuenta();
        $this->autorizar($this->actor, ['administrar usuarios']);
        $this->actingAs($this->actor);
    }

    private function cuenta(): User
    {
        $n = User::count() + 1;
        return User::create(['name' => 'Persona ficticia '.$n, 'rut' => (string) (10000000 + $n), 'email' => "cuenta$n@example.test", 'password' => 'ClaveFicticia1234']);
    }

    private function autorizar(User|Role $modelo, array $permisos): void
    {
        $modelo->givePermissionTo(collect($permisos)->map(fn ($p) => Permission::findOrCreate($p, 'web')));
    }

    private function perfil(string $nombre = 'Consulta'): Role
    {
        $p = Role::findOrCreate($nombre, 'web');
        $this->autorizar($p, ['ver programacion semestral']);
        return $p;
    }

    private function datos(?Role $perfil = null, array $cambios = []): array
    {
        return $cambios + ['name' => $perfil?->name ?? 'Consulta académica', 'permisos' => ['ver panel inicio', 'ver programacion semestral'], 'firma' => $perfil ? app(GestionPerfiles::class)->firma($perfil) : null];
    }

    public function test_crea_perfil_y_registra_permisos_sin_modificar_cuentas(): void
    {
        $this->post(route('administracion.perfiles.store'), $this->datos(null, ['name' => '  Consulta   académica  ']))->assertRedirect(route('administracion.perfiles.index'))->assertSessionHasNoErrors();
        $p = Role::where('name', 'Consulta académica')->sole();
        $this->assertEqualsCanonicalizing(['ver panel inicio', 'ver programacion semestral'], $p->permissions->pluck('name')->all());
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Crear perfil de acceso', 'registro_id' => $p->id]);
        $this->get(route('administracion.perfiles.index'))->assertOk()->assertSee('Consulta académica')->assertSee('Alcance institucional');
    }

    public function test_unifica_los_perfiles_docentes_y_conserva_los_otros_accesos_de_cada_cuenta(): void
    {
        $docente = Role::findOrCreate('Docente', 'web');
        $this->autorizar($docente, ['enviar propuesta horario']);
        $anterior = Role::findOrCreate('Docente con consulta', 'web');
        $this->autorizar($anterior, ['enviar propuesta horario', 'ver programacion semestral']);
        $otroPerfil = $this->perfil('Consulta histórica');
        $this->autorizar($otroPerfil, ['ver programacion anterior']);
        $usuario = $this->cuenta();
        $usuario->assignRole([$docente, $anterior, $otroPerfil]);
        $this->autorizar($usuario, ['ver intercambio docente']);
        $hash = $usuario->password;
        $migracion = require database_path('migrations/2026_11_16_220000_unifica_perfil_docente.php');

        $migracion->up();
        $migracion->up();
        $usuario->refresh();

        $this->assertDatabaseMissing('roles', ['name' => 'Docente con consulta']);
        $this->assertSame(1, Role::where('name', 'Docente')->count());
        $this->assertEqualsCanonicalizing(['Docente', 'Consulta histórica'], $usuario->getRoleNames()->all());
        $this->assertTrue($usuario->can('enviar propuesta horario'));
        $this->assertTrue($usuario->can('ver programacion semestral'));
        $this->assertTrue($usuario->can('ver programacion anterior'));
        $this->assertTrue($usuario->can('ver intercambio docente'));
        $this->assertSame($hash, $usuario->password);
        $this->get(route('administracion.perfiles.index'))->assertOk()
            ->assertSee('Docente')->assertDontSee('Docente con consulta');
    }

    public function test_formularios_y_navegacion_muestran_alcance_y_cuentas(): void
    {
        $p = $this->perfil(); $u = $this->cuenta(); $u->assignRole($p);
        $this->get(route('administracion.perfiles.create'))->assertOk()->assertSee('Importación de Excel')->assertSee('Los campos con * son obligatorios.');
        $this->get(route('administracion.perfiles.edit', $p))->assertOk()->assertSee($u->name)->assertSee('cuenta vinculada')->assertSee('todas las carreras');
        $this->get(route('administracion.index'))->assertOk()->assertSee(route('administracion.perfiles.index'));
        $this->get(route('usuarios.create'))->assertOk()->assertSee($p->name);
    }

    public function test_sin_permiso_no_consulta_ni_modifica_perfiles(): void
    {
        $p = $this->perfil(); $u = $this->cuenta(); $this->autorizar($u, ['ver programacion semestral']); $this->actingAs($u);
        $this->get(route('administracion.perfiles.index'))->assertRedirect(route('programacion.semestral'));
        $this->get(route('administracion.perfiles.create'))->assertRedirect();
        $this->get(route('administracion.perfiles.edit', $p))->assertRedirect();
        $this->postJson(route('administracion.perfiles.store'), $this->datos())->assertForbidden();
        $this->putJson(route('administracion.perfiles.update', $p), $this->datos($p))->assertForbidden();
        $this->assertDatabaseCount('roles', 1); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_anonimos_no_entran(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get(route('administracion.perfiles.index'))->assertRedirect(route('login'));
        $this->post(route('administracion.perfiles.store'), $this->datos())->assertRedirect(route('login'));
    }

    public function test_valida_nombre_permisos_y_dependencias(): void
    {
        foreach ([['name' => '  '], ['name' => str_repeat('a', 81)], ['name' => '<script>'], ['name' => 'ADMINISTRADOR'], ['permisos' => []], ['permisos' => ['permiso inventado']], ['permisos' => ['ver panel inicio', 'ver panel inicio']], ['permisos' => ['asignar horarios']], ['permisos' => ['importar programacion anterior']], ['permisos' => 'ver panel inicio']] as $cambios) {
            $this->postJson(route('administracion.perfiles.store'), $this->datos(null, $cambios))->assertUnprocessable();
            $this->assertDatabaseCount('roles', 0); $this->assertDatabaseCount('historial_acciones', 0);
        }
    }

    public function test_no_duplica_nombres_por_mayusculas_y_espacios(): void
    {
        $this->perfil('Consulta académica');
        $this->post(route('administracion.perfiles.store'), $this->datos(null, ['name' => '  CONSULTA  académica ']))->assertSessionHasErrors('name');
        $this->post(route('administracion.perfiles.store'), $this->datos(null, ['name' => 'Consulta academica']))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('roles', 1);
    }

    public function test_actualiza_perfil_sin_cuentas_y_conserva_permisos_ajenos_al_catalogo(): void
    {
        $p = $this->perfil(); $this->autorizar($p, ['extensión institucional']);
        $this->get(route('administracion.perfiles.edit', $p))->assertOk()->assertSee('Permisos adicionales conservados')->assertSee('extensión institucional');
        $this->put(route('administracion.perfiles.update', $p), $this->datos($p, ['name' => 'Consulta nueva', 'permisos' => ['ver panel inicio']]))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['ver panel inicio', 'extensión institucional'], $p->fresh()->permissions->pluck('name')->all());
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Actualizar perfil de acceso']);
    }

    public function test_perfil_con_cuentas_exige_confirmacion_y_aplica_cambio(): void
    {
        $p = $this->perfil(); $u = $this->cuenta(); $u->assignRole($p);
        $datos = $this->datos($p, ['permisos' => ['ver panel inicio']]);
        $this->put(route('administracion.perfiles.update', $p), $datos)->assertSessionHasErrors('confirmar');
        $this->assertTrue($u->fresh()->can('ver programacion semestral'));
        $this->put(route('administracion.perfiles.update', $p), $datos + ['confirmar' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->can('ver panel inicio')); $this->assertFalse($u->fresh()->can('ver programacion semestral'));
        $this->assertSame(1, json_decode(DB::table('historial_acciones')->sole()->datos, true)['cuentas_afectadas']);
    }

    public function test_administrador_es_solo_consulta_incluso_en_envio_directo(): void
    {
        $p = $this->perfil('Administrador');
        $this->get(route('administracion.perfiles.edit', $p))->assertOk()->assertSee('Permisos de Administrador')->assertDontSee('Guardar cambios');
        $this->put(route('administracion.perfiles.update', $p), $this->datos($p, ['name' => 'Otro perfil']))->assertSessionHasErrors('name');
        $this->assertSame('Administrador', $p->fresh()->name); $this->assertTrue($p->fresh()->hasPermissionTo('ver programacion semestral'));
    }

    public function test_rechaza_edicion_con_permisos_o_cuentas_desactualizadas(): void
    {
        $p = $this->perfil(); $datos = $this->datos($p); $this->autorizar($p, ['ver panel inicio']);
        $this->put(route('administracion.perfiles.update', $p), $datos)->assertSessionHasErrors('firma');
        $datos = $this->datos($p); $this->cuenta()->assignRole($p);
        $this->put(route('administracion.perfiles.update', $p), $datos + ['confirmar' => '1'])->assertSessionHasErrors('firma');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_se_puede_quitar_el_acceso_administrativo_del_operador(): void
    {
        $p = $this->perfil('Gestión'); $this->autorizar($p, ['administrar usuarios']);
        $this->actor->revokePermissionTo('administrar usuarios'); $this->actor->assignRole($p);
        $this->put(route('administracion.perfiles.update', $p), $this->datos($p, ['confirmar' => '1']))->assertSessionHasErrors('permisos');
        $this->assertTrue($this->actor->fresh()->can('administrar usuarios')); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_permiso_directo_conservado_permite_modificar_su_perfil(): void
    {
        $p = $this->perfil('Gestión'); $this->autorizar($p, ['administrar usuarios']); $this->actor->assignRole($p);
        $this->put(route('administracion.perfiles.update', $p), $this->datos($p, ['confirmar' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue($this->actor->fresh()->can('administrar usuarios')); $this->assertFalse($p->fresh()->permissions->contains('name', 'administrar usuarios'));
    }

    public function test_otro_guard_no_se_muestra_ni_se_edita_ni_se_asigna_a_cuenta(): void
    {
        $p = Role::create(['name' => 'Perfil externo', 'guard_name' => 'api']);
        $this->get(route('administracion.perfiles.index'))->assertOk()->assertDontSee('Perfil externo');
        $this->get(route('administracion.perfiles.edit', $p))->assertNotFound();
        $this->put(route('administracion.perfiles.update', $p), $this->datos())->assertNotFound();
        $this->get(route('usuarios.create'))->assertOk()->assertDontSee('Perfil externo');
        $this->post(route('usuarios.store'), ['name' => 'Ficticio', 'rut' => '88777777', 'email' => 'nuevo@example.test', 'password' => 'ClaveFicticia1234', 'password_confirmation' => 'ClaveFicticia1234', 'role' => $p->name])->assertSessionHasErrors('role');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_fallo_de_auditoria_revierte_perfil_y_permisos(): void
    {
        $p = $this->perfil();
        DB::unprepared("CREATE TRIGGER falla_perfil BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $this->withoutExceptionHandling();
        try { $this->put(route('administracion.perfiles.update', $p), $this->datos($p, ['name' => 'Cambiado', 'permisos' => ['ver panel inicio']])) ; $this->fail(); }
        catch (\Illuminate\Database\QueryException) { $this->assertSame('Consulta', $p->fresh()->name); }
        $this->assertTrue($p->fresh()->hasPermissionTo('ver programacion semestral'));
        $this->assertFalse($p->fresh()->permissions->contains('name', 'ver panel inicio'));
    }

    public function test_cada_permiso_tiene_pantalla_de_entrada_y_descripcion(): void
    {
        $this->assertEqualsCanonicalizing(PermisosSistema::NOMBRES, collect(PermisosSistema::grupos())->collapse()->keys()->all());
        foreach (PermisosSistema::NOMBRES as $p) {
            $u = $this->cuenta(); $this->autorizar($u, [$p]);
            $ruta = PermisosSistema::entrada($u);
            $this->assertNotNull($ruta, $p);
            $this->post(route('login.procesar'), ['rut' => $u->rut, 'password' => 'ClaveFicticia1234'])->assertRedirect(route($ruta));
            $this->assertAuthenticatedAs($u);
            $this->assertContains($this->get(route('inicio'))->status(), [200, 302]);
            $this->assertAuthenticatedAs($u);
        }
    }

    public function test_login_sin_permisos_cierra_la_sesion_con_mensaje(): void
    {
        $u = $this->cuenta();
        $this->post(route('login.procesar'), ['rut' => $u->rut, 'password' => 'ClaveFicticia1234'])->assertRedirect(route('login'))->assertSessionHasErrors('rut');
        $this->assertGuest();
    }

    public function test_consulta_anterior_no_autoriza_importacion_ni_muestra_sus_enlaces(): void
    {
        $u = $this->cuenta(); $this->autorizar($u, ['ver programacion anterior']); $this->actingAs($u);
        $this->get(route('programacion.index'))->assertOk()->assertDontSee('Cargar programación anterior');
        $this->get(route('programacion.importar'))->assertRedirect(route('programacion.index'));
        foreach (['analizar', 'confirmar', 'cancelar'] as $accion) $this->postJson(route('programacion.importar.'.$accion), [])->assertForbidden();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_importador_puede_abrir_formulario_y_enlaces(): void
    {
        $this->autorizar($this->actor, ['ver programacion anterior', 'importar programacion anterior']);
        $this->get(route('programacion.importar'))->assertOk();
        $this->get(route('programacion.index'))->assertOk()->assertSee('Cargar programación anterior');
    }

    public function test_inicio_no_expone_el_excel_por_la_antigua_ruta_de_prueba(): void
    {
        $u = $this->cuenta(); $this->autorizar($u, ['ver panel inicio']); $this->actingAs($u);
        $this->get('/prueba-excel')->assertRedirect(route('login'))->assertDontSee('filas_primera_hoja');
    }

    public function test_migracion_conserva_acceso_previo_de_roles_y_permisos_directos_sin_tocar_cuentas(): void
    {
        $p = $this->perfil(); $this->autorizar($p, ['ver programacion anterior']);
        $directo = $this->cuenta(); $this->autorizar($directo, ['ver programacion anterior']);
        $sinAcceso = $this->cuenta(); $sinAcceso->assignRole($this->perfil('Consulta semestral'));
        $hash = $directo->password;
        $migracion = require database_path('migrations/2026_11_15_180000_separa_permiso_importacion.php');
        $migracion->up(); $migracion->up();
        $this->assertTrue($p->fresh()->hasPermissionTo('importar programacion anterior'));
        $this->assertTrue($directo->fresh()->can('importar programacion anterior'));
        $this->assertFalse($sinAcceso->fresh()->can('importar programacion anterior'));
        $this->assertSame($hash, $directo->fresh()->password); $this->assertTrue(Hash::check('ClaveFicticia1234', $hash));
        $this->assertSame(1, Permission::where('name', 'importar programacion anterior')->count());
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_hay_permisos_de_rutas_fuera_del_catalogo(): void
    {
        $permisos = collect(Route::getRoutes())->flatMap(fn ($r) => $r->gatherMiddleware())->filter(fn ($p) => is_string($p) && str_starts_with($p, 'permission.redirect:'))->map(fn ($p) => substr($p, 20))->unique();
        foreach ($permisos as $p) $this->assertContains($p, PermisosSistema::NOMBRES);
    }

    public function test_auditoria_de_cuentas_no_guarda_claves_y_es_atomica(): void
    {
        $p = $this->perfil();
        $datos = ['name' => 'Ficticio', 'rut' => '88777777', 'email' => 'nuevo@example.test', 'password' => 'ClaveFicticia1234', 'password_confirmation' => 'ClaveFicticia1234', 'role' => $p->name];
        $this->post(route('usuarios.store'), $datos)->assertSessionHasNoErrors();
        $auditoria = DB::table('historial_acciones')->sole();
        $this->assertStringNotContainsString($datos['password'], $auditoria->datos);
        $this->assertStringNotContainsString('password', $auditoria->datos);
        DB::unprepared("CREATE TRIGGER falla_cuenta BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $this->withoutExceptionHandling();
        try { $this->post(route('usuarios.store'), array_replace($datos, ['rut' => '88777778', 'email' => 'otro@example.test'])); $this->fail(); }
        catch (\Illuminate\Database\QueryException) { $this->assertDatabaseCount('users', 2); }
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_la_cuenta_no_puede_perder_su_propio_acceso_administrativo_al_cambiar_perfil(): void
    {
        $p = $this->perfil('Gestión'); $this->autorizar($p, ['administrar usuarios']);
        $this->actor->assignRole($p); $this->actor->revokePermissionTo('administrar usuarios');
        $consulta = $this->perfil();
        $this->put(route('usuarios.update', $this->actor), ['name' => $this->actor->name, 'rut' => $this->actor->rut, 'email' => $this->actor->email, 'role' => $consulta->name])->assertSessionHasErrors('role');
        $this->assertTrue($this->actor->fresh()->can('administrar usuarios'));
    }
}
