<?php

namespace App\Services;

use App\Models\User;
use App\Support\PermisosSistema;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

class GestionPerfiles
{
    public function protegido(Role $perfil): bool
    {
        return Str::lower(Str::squish($perfil->name)) === 'administrador';
    }

    public function firma(Role $perfil): string
    {
        $perfil = $perfil->fresh('permissions');
        return hash('sha256', json_encode([
            $perfil->id, $perfil->name, $perfil->guard_name,
            $perfil->permissions->sortBy('id')->pluck('id')->values()->all(),
            $perfil->users()->orderBy('users.id')->pluck('users.id')->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function guardar(array $entrada, User $actor, ?Role $perfil = null): Role
    {
        if (is_string($entrada['name'] ?? null)) {
            $entrada['name'] = preg_replace_callback('/\bprofesor(?:as|es|a)?\b/iu',
                fn ($nombre) => preg_match('/(?:as|es)\z/iu', $nombre[0]) ? 'Docentes' : 'Docente',
                Str::squish($entrada['name']));
            if (Str::lower($entrada['name']) === 'docente con consulta') {
                $entrada['name'] = 'Docente';
            }
        }
        $datos = Validator::make($entrada, [
            'name' => ['required', 'string', 'max:80', 'not_regex:/[<>\x00-\x1f]/u'],
            'permisos' => ['required', 'array', 'min:1', 'max:'.count(PermisosSistema::NOMBRES)],
            'permisos.*' => ['required', 'string', 'distinct', Rule::in(PermisosSistema::NOMBRES)],
            'firma' => [$perfil ? 'required' : 'nullable', 'string', 'size:64'],
            'confirmar' => ['sometimes', 'accepted'],
        ], [
            'permisos.required' => 'Selecciona al menos un permiso.',
            'permisos.*.in' => 'Uno de los permisos seleccionados no pertenece al sistema.',
            'name.not_regex' => 'Usa un nombre de perfil sin etiquetas ni caracteres de control.',
            'name.required' => 'Indica el nombre del perfil.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'permisos.array' => 'Selecciona los permisos desde las opciones del formulario.',
            'permisos.*.distinct' => 'No repitas permisos en la selección.',
            'firma.required' => 'Recarga el formulario antes de guardar.',
            'firma.size' => 'La revisión del perfil no es válida. Recarga el formulario.',
            'confirmar.accepted' => 'Confirma que revisaste el efecto sobre las cuentas del perfil.',
        ])->validate();

        try {
            return DB::transaction(function () use ($datos, $actor, $perfil) {
                // El mismo orden se utiliza al asignar perfiles a las cuentas.
                $roles = Role::orderBy('id')->lockForUpdate()->get();
                $actor = User::lockForUpdate()->findOrFail($actor->id);
                abort_unless($actor->can('administrar usuarios'), 403);
                if ($perfil) {
                    $perfil = $roles->firstWhere('id', $perfil->id);
                    abort_unless($perfil && $perfil->guard_name === 'web', 404);
                    if ($this->protegido($perfil)) $this->rechazar('name', 'El perfil Administrador está protegido. Sus permisos se conservan.');
                    if (! hash_equals($this->firma($perfil), $datos['firma'])) $this->rechazar('firma', 'El perfil o sus cuentas cambiaron. Recarga la página y revisa nuevamente antes de guardar.');
                    if ($perfil->users()->exists() && empty($datos['confirmar'])) $this->rechazar('confirmar', 'Confirma que revisaste el efecto sobre las cuentas de este perfil.');
                }
                $nombre = Str::lower(Str::ascii($datos['name']));
                if ($nombre === 'administrador' || $roles->contains(fn ($r) => $r->guard_name === 'web' && $r->id !== $perfil?->id && Str::lower(Str::ascii(Str::squish($r->name))) === $nombre)) {
                    $this->rechazar('name', 'Ya existe un perfil con ese nombre o está reservado.');
                }
                $permisos = $datos['permisos'];
                foreach (PermisosSistema::DEPENDENCIAS as $permiso => $lectura) {
                    if (in_array($permiso, $permisos, true) && ! in_array($lectura, $permisos, true)) {
                        $etiquetas = collect(PermisosSistema::grupos())->collapse();
                        $this->rechazar('permisos', 'Para permitir «'.$etiquetas[$permiso][0].'», selecciona también «'.$etiquetas[$lectura][0].'».');
                    }
                }
                $antes = $perfil ? ['nombre' => $perfil->name, 'permisos' => $perfil->permissions->pluck('name')->sort()->values()->all()] : null;
                // Los permisos de extensiones ajenas al catálogo se muestran y conservan.
                $adicionales = $perfil ? $perfil->permissions->whereNotIn('name', PermisosSistema::NOMBRES) : collect();
                $modelos = collect($permisos)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->merge($adicionales);
                $nuevo = $perfil === null;
                $perfil ??= new Role(['guard_name' => 'web']);
                $perfil->name = $datos['name'];
                $perfil->save();
                $perfil->syncPermissions($modelos);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                if (! $actor->fresh()->can('administrar usuarios')) $this->rechazar('permisos', 'Conserva tu permiso para administrar accesos. Otro administrador puede realizar este cambio.');
                DB::table('historial_acciones')->insert([
                    'usuario_id' => $actor->id, 'accion' => $nuevo ? 'Crear perfil de acceso' : 'Actualizar perfil de acceso',
                    'modulo' => 'Administración', 'tabla_afectada' => 'roles', 'registro_id' => $perfil->id,
                    'descripcion' => 'Permisos institucionales del perfil '.$perfil->name.'.',
                    'datos' => json_encode(['antes' => $antes, 'despues' => ['nombre' => $perfil->name, 'permisos' => $perfil->fresh('permissions')->permissions->pluck('name')->sort()->values()->all()], 'cuentas_afectadas' => $perfil->users()->count()], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return $perfil;
            }, 3);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
