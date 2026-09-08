<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('horarios', 'grupo_seccion_id')) {
            Schema::table('horarios', function (Blueprint $table) {
                $table->foreignId('grupo_seccion_id')
                    ->nullable()
                    ->after('actividad_seccion_id')
                    ->constrained('grupos_seccion')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('horarios', 'grupo_seccion_id')) {
            Schema::table('horarios', function (Blueprint $table) {
                $table->dropForeign(['grupo_seccion_id']);
                $table->dropColumn('grupo_seccion_id');
            });
        }
    }
};