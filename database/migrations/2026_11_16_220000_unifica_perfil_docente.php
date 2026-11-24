<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $perfiles = Role::where('guard_name', 'web')->orderBy('id')->lockForUpdate()->get()
                ->filter(fn ($perfil) => in_array($perfil->name, ['Docente', 'Docente con consulta', 'Profesor'], true));
            if ($perfiles->isEmpty()) return;

            $docente = $perfiles->firstWhere('name', 'Docente') ?? $perfiles->first();
            $docente->update(['name' => 'Docente']);
            $docente->givePermissionTo([
                Permission::findOrCreate('enviar propuesta horario', 'web'),
                Permission::findOrCreate('ver programacion semestral', 'web'),
            ]);
            $docente->load('permissions');

            foreach ($perfiles->reject(fn ($perfil) => $perfil->is($docente)) as $anterior) {
                $adicionales = $anterior->permissions->whereNotIn('id', $docente->permissions->modelKeys());
                foreach ($anterior->users()->orderBy('users.id')->lockForUpdate()->get() as $usuario) {
                    if ($adicionales->isNotEmpty()) {
                        $usuario->givePermissionTo($adicionales);
                    }
                    $usuario->assignRole($docente);
                    $usuario->removeRole($anterior);
                }
                $anterior->delete();
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // La consolidación de cuentas y permisos se mantiene al revertir el código.
    }
};
