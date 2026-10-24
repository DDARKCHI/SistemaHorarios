<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\IsolatedDatabaseTestCase;

class AdministracionUsuariosTest extends IsolatedDatabaseTestCase
{
    private User $gestor;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = $this->crearUsuario('Encargado', 'Profesor');
        $this->gestor->givePermissionTo(Permission::findOrCreate('administrar usuarios', 'web'));
        $this->administrador = $this->crearUsuario('Administrador principal', 'Administrador');
        $this->actingAs($this->gestor);
    }

    public function test_no_cambia_el_rol_del_ultimo_administrador_ni_guarda_parcialmente_sus_datos(): void
    {
        $antes = $this->administrador->fresh()->getRawOriginal();
        $datos = $this->datos($this->administrador, 'Profesor') + [
            'password' => 'otra-clave-segura', 'password_confirmation' => 'otra-clave-segura',
        ];
        $datos['name'] = 'Nombre cambiado';
        $datos['rut'] = '29999999';
        $datos['email'] = 'cambiado@example.test';

        $this->from(route('usuarios.edit', $this->administrador))
            ->put(route('usuarios.update', $this->administrador), $datos)
            ->assertRedirect(route('usuarios.edit', $this->administrador))
            ->assertSessionHasErrors('role');

        $this->assertSame($antes, $this->administrador->fresh()->getRawOriginal());
        $this->assertTrue($this->administrador->fresh()->hasRole('Administrador'));
        $this->assertSame(1, User::role('Administrador')->count());
        $this->get(route('usuarios.edit', $this->administrador))
            ->assertOk()->assertSee('Debe quedar al menos un administrador.');
    }

    public function test_permite_editar_datos_del_unico_administrador_si_conserva_su_rol(): void
    {
        $datos = $this->datos($this->administrador, 'Administrador');
        $datos['name'] = 'Nombre actualizado';

        $this->put(route('usuarios.update', $this->administrador), $datos)
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre actualizado', $this->administrador->fresh()->name);
        $this->assertTrue($this->administrador->fresh()->hasRole('Administrador'));
    }

    public function test_permite_cambiar_el_rol_si_queda_otro_administrador(): void
    {
        $this->crearUsuario('Segundo administrador', 'Administrador');

        $this->put(route('usuarios.update', $this->administrador), $this->datos($this->administrador, 'Profesor'))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->administrador->fresh()->hasRole('Profesor'));
        $this->assertSame(1, User::role('Administrador')->count());
    }

    public function test_tras_quitar_un_administrador_protege_al_que_queda(): void
    {
        $segundo = $this->crearUsuario('Segundo administrador', 'Administrador');
        $this->put(route('usuarios.update', $this->administrador), $this->datos($this->administrador, 'Profesor'))
            ->assertSessionHasNoErrors();

        $this->put(route('usuarios.update', $segundo), $this->datos($segundo, 'Profesor'))
            ->assertSessionHasErrors('role');

        $this->assertTrue($segundo->fresh()->hasRole('Administrador'));
        $this->assertSame(1, User::role('Administrador')->count());
    }

    public function test_no_elimina_al_ultimo_administrador(): void
    {
        $this->delete(route('usuarios.destroy', $this->administrador))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHas('error', 'No puedes eliminar al último administrador. Asigna este rol a otro usuario primero.');

        $this->assertDatabaseHas('users', ['id' => $this->administrador->id]);
        $this->assertTrue($this->administrador->fresh()->hasRole('Administrador'));
        $this->get(route('administracion.index'))->assertOk()->assertSee('No puedes eliminar al último administrador.');
    }

    public function test_permite_eliminar_un_administrador_si_queda_otro(): void
    {
        $this->crearUsuario('Segundo administrador', 'Administrador');

        $this->delete(route('usuarios.destroy', $this->administrador))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $this->administrador->id]);
        $this->assertSame(1, User::role('Administrador')->count());
    }

    public function test_permite_cambiar_el_rol_de_un_usuario_que_no_es_administrador(): void
    {
        $usuario = $this->crearUsuario('Docente', 'Profesor');
        Role::findOrCreate('Asistente académico', 'web');

        $this->put(route('usuarios.update', $usuario), $this->datos($usuario, 'Asistente académico'))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($usuario->fresh()->hasRole('Asistente académico'));
        $this->assertSame(1, User::role('Administrador')->count());
    }

    public function test_permite_eliminar_un_usuario_que_no_es_administrador(): void
    {
        $usuario = $this->crearUsuario('Docente', 'Profesor');

        $this->delete(route('usuarios.destroy', $usuario))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
        $this->assertSame(1, User::role('Administrador')->count());
    }

    public function test_conserva_la_proteccion_contra_eliminar_el_propio_usuario(): void
    {
        $this->gestor->assignRole('Administrador');

        $this->delete(route('usuarios.destroy', $this->gestor))
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHas('error', 'No puedes eliminar tu propio usuario.');

        $this->assertDatabaseHas('users', ['id' => $this->gestor->id]);
    }

    public function test_el_unico_administrador_tampoco_puede_quitarse_su_propio_rol(): void
    {
        $this->administrador->syncRoles('Profesor');
        $this->gestor->syncRoles('Administrador');

        $this->put(route('usuarios.update', $this->gestor), $this->datos($this->gestor, 'Profesor'))
            ->assertSessionHasErrors('role');

        $this->assertTrue($this->gestor->fresh()->hasRole('Administrador'));
        $this->assertSame(1, User::role('Administrador')->count());
    }

    private function crearUsuario(string $nombre, string $rol): User
    {
        $numero = User::count() + 1;
        $usuario = User::create([
            'name' => $nombre, 'rut' => (string) (10000000 + $numero),
            'email' => "usuario$numero@example.test", 'password' => 'clave-de-prueba',
        ]);
        $usuario->assignRole(Role::findOrCreate($rol, 'web'));

        return $usuario;
    }

    private function datos(User $usuario, string $rol): array
    {
        return ['name' => $usuario->name, 'rut' => $usuario->rut, 'email' => $usuario->email, 'role' => $rol];
    }
}
