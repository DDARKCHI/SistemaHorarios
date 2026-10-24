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
        $roles = Role::orderBy('name')->get();

        return view('administracion.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:20', 'unique:users,rut'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'exists:roles,name'],
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

        $usuario = User::create([
            'name' => $datos['name'],
            'rut' => $datos['rut'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);

        $usuario->syncRoles([$datos['role']]);

        return redirect()
            ->route('administracion.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $roles = Role::orderBy('name')->get();

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
            'role' => ['required', 'exists:roles,name'],
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
            $usuario->syncRoles([$datos['role']]);
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
        return Role::where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();
    }

    private function esUltimoAdministrador(User $usuario, ?Role $rolAdministrador): bool
    {
        return $rolAdministrador !== null
            && $usuario->roles()->whereKey($rolAdministrador->id)->exists()
            && User::role($rolAdministrador)->count() <= 1;
    }
}
