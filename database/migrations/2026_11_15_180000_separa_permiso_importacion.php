<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Antes, consultar programación anterior también autorizaba importar.
            // Conservar ese acceso existente, sin asignarlo a futuros perfiles de consulta.
            if (DB::table('permissions')->where('guard_name', 'web')->where('name', 'importar programacion anterior')->exists()) return;
            $anterior = DB::table('permissions')->where('guard_name', 'web')->where('name', 'ver programacion anterior')->value('id');
            if (! $anterior) return;
            $nuevo = DB::table('permissions')->insertGetId(['name' => 'importar programacion anterior', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('role_has_permissions')->where('permission_id', $anterior)->get() as $fila) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $nuevo, 'role_id' => $fila->role_id]);
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $anterior)->get() as $fila) {
                DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $nuevo, 'model_type' => $fila->model_type, 'model_id' => $fila->model_id]);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Conservar permisos y decisiones administrativas ante una reversión del código.
    }
};
