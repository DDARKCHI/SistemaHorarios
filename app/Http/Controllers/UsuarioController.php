<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::with('roles')
            ->orderBy('name')
            ->get();

        return view('administracion.index', compact('usuarios'));
    }

    public function create()
    {
        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();

        return view('administracion.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:20', 'unique:users,rut'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser válido.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',

            'rut.required' => 'El RUT es obligatorio.',
            'rut.string' => 'El RUT debe ser válido.',
            'rut.max' => 'El RUT no puede superar los 20 caracteres.',
            'rut.unique' => 'Este RUT ya se encuentra registrado.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser válida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',

            'role.required' => 'Debes seleccionar un rol.',
            'role.exists' => 'El rol seleccionado no es válido.',
        ]);

        DB::transaction(function () use ($datos) {
            $this->bloquearRolAdministrador();
            $rol = Role::where('guard_name', 'web')->where('name', $datos['role'])->first();
            if (! $rol) throw ValidationException::withMessages(['role' => 'El perfil cambió. Revisa la selección.']);
            $usuario = User::create(collect($datos)->only(['name', 'rut', 'email', 'password'])->all());
            $usuario->syncRoles([$rol]);
            $this->auditar('Crear cuenta', $usuario, ['perfil' => $rol->name]);
        }, 3);

        return redirect()
            ->route('administracion.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $roles = Role::where('guard_name', 'web')->orderBy('name')->get();

        return view('administracion.edit', compact('usuario', 'roles'));
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rut' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'rut')->ignore($usuario->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser válido.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',

            'rut.required' => 'El RUT es obligatorio.',
            'rut.string' => 'El RUT debe ser válido.',
            'rut.max' => 'El RUT no puede superar los 20 caracteres.',
            'rut.unique' => 'Este RUT ya se encuentra registrado.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',

            'password.string' => 'La contraseña debe ser válida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',

            'role.required' => 'Debes seleccionar un rol.',
            'role.exists' => 'El rol seleccionado no es válido.',
        ]);

        DB::transaction(function () use ($datos, $usuario) {
            $rolAdministrador = $this->bloquearRolAdministrador();
            $usuario = User::query()->lockForUpdate()->findOrFail($usuario->id);
            $rol = Role::where('guard_name', 'web')->where('name', $datos['role'])->first();
            if (! $rol) throw ValidationException::withMessages(['role' => 'El perfil cambió. Revisa la selección.']);
            $rolesAntes = $usuario->getRoleNames()->sort()->values()->all();

            if ($datos['role'] !== 'Administrador' && $this->esUltimoAdministrador($usuario, $rolAdministrador)) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un administrador. Asigna este rol a otro usuario antes de cambiarlo.',
                ]);
            }

            $usuario->name = $datos['name'];
            $usuario->rut = $datos['rut'];
            $usuario->email = $datos['email'];

            if (!empty($datos['password'])) {
                $usuario->password = $datos['password'];
            }

            $usuario->save();
            $usuario->syncRoles([$rol]);
            if ($usuario->id === Auth::id() && ! $usuario->fresh()->can('administrar usuarios')) {
                throw ValidationException::withMessages(['role' => 'Conserva tu permiso para administrar accesos. Otro administrador puede cambiar tu perfil.']);
            }
            $this->auditar('Actualizar cuenta', $usuario, ['perfiles_antes' => $rolesAntes, 'perfil' => $rol->name, 'clave_actualizada' => ! empty($datos['password'])]);
        });

        return redirect()
            ->route('administracion.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === Auth::id()) {
            return redirect()
                ->route('administracion.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $eliminado = DB::transaction(function () use ($usuario) {
            $rolAdministrador = $this->bloquearRolAdministrador();
            $usuario = User::query()->lockForUpdate()->findOrFail($usuario->id);

            if ($this->esUltimoAdministrador($usuario, $rolAdministrador)) {
                return false;
            }

            $this->auditar('Eliminar cuenta', $usuario, ['perfiles' => $usuario->getRoleNames()->sort()->values()->all()]);
            $usuario->delete();

            return true;
        });

        if (!$eliminado) {
            return redirect()
                ->route('administracion.index')
                ->with('error', 'No puedes eliminar al último administrador. Asigna este rol a otro usuario primero.');
        }

        return redirect()
            ->route('administracion.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    private function bloquearRolAdministrador(): ?Role
    {
        // Compartir este bloqueo evita retirar a los últimos administradores en solicitudes simultáneas.
        return Role::orderBy('id')->lockForUpdate()->get()
            ->first(fn ($rol) => $rol->name === 'Administrador' && $rol->guard_name === 'web');
    }

    private function auditar(string $accion, User $usuario, array $datos): void
    {
        DB::table('historial_acciones')->insert([
            'usuario_id' => Auth::id(), 'accion' => $accion, 'modulo' => 'Administración',
            'tabla_afectada' => 'users', 'registro_id' => $usuario->id,
            'descripcion' => 'Gestión de la cuenta '.$usuario->id.'.',
            'datos' => json_encode($datos, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function esUltimoAdministrador(User $usuario, ?Role $rolAdministrador): bool
    {
        return $rolAdministrador !== null
            && $usuario->roles()->whereKey($rolAdministrador->id)->exists()
            && User::role($rolAdministrador)->count() <= 1;
    }
}
