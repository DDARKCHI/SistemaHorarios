<?php

namespace App\Services;

use App\Models\{Universidad, User};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

class InstalacionInicial
{
    public const PERMISOS = \App\Support\PermisosSistema::NOMBRES;

    public function comprobar(): void
    {
        foreach (['users', 'universidades', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'historial_acciones'] as $tabla) {
            if (! Schema::hasTable($tabla)) $this->rechazar('Aplica primero las migraciones normales en la base de esta instalación.');
        }
        if (User::query()->exists()) $this->rechazar('Esta instalación ya tiene usuarios. Gestiona las cuentas desde Administración o utiliza la recuperación de contraseña.');
        if (! Universidad::where('estado', true)->exists()) $this->rechazar('Se requiere una institución activa antes de preparar el acceso inicial.');
    }

    public function crear(#[\SensitiveParameter] array $entrada): User
    {
        $this->comprobar();
        foreach (['name', 'rut', 'email'] as $campo) if (is_string($entrada[$campo] ?? null)) $entrada[$campo] = Str::squish($entrada[$campo]);
        if (is_string($entrada['email'] ?? null)) $entrada['email'] = mb_strtolower($entrada['email']);
        $datos = Validator::make($entrada, [
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:20', 'regex:/\A[0-9]{5,10}\z/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
        ], ['rut.regex' => 'Indica el RUT de acceso sin puntos, guion ni dígito verificador.'])->validate();

        return DB::transaction(function () use ($datos) {
            // La institución inicial existe desde la migración y serializa dos altas iniciales.
            Universidad::orderBy('id')->lockForUpdate()->firstOrFail();
            $this->comprobar();
            $rol = Role::findOrCreate('Administrador', 'web');
            $permisos = collect(self::PERMISOS)->map(fn ($p) => Permission::findOrCreate($p, 'web'));
            $rol->givePermissionTo($permisos);
            $usuario = User::create(collect($datos)->only(['name', 'rut', 'email', 'password'])->all());
            $usuario->assignRole($rol);
            DB::table('historial_acciones')->insert([
                'usuario_id' => $usuario->id, 'accion' => 'Inicializar acceso administrativo', 'modulo' => 'Instalación',
                'tabla_afectada' => 'users', 'registro_id' => $usuario->id,
                'descripcion' => 'Primera cuenta de una instalación sin usuarios, creada desde consola.',
                'datos' => json_encode(['rol' => 'Administrador', 'permisos' => self::PERMISOS], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            return $usuario;
        }, 3);
    }

    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['instalacion' => $mensaje]); }
}
