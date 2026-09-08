<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('horarios')) {
            return;
        }

        Schema::table('horarios', function (Blueprint $table) {
            $table->index(
                'actividad_seccion_id',
                'horarios_actividad_seccion_id_index'
            );
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropUnique('horario_actividad_unique');
        });

        if (Schema::hasColumn('horarios', 'grupo')) {
            Schema::table('horarios', function (Blueprint $table) {
                $table->dropColumn('grupo');
            });
        }

        Schema::table('horarios', function (Blueprint $table) {
            $table->unique(
                [
                    'actividad_seccion_id',
                    'grupo_seccion_id',
                    'version_planificacion_id',
                    'dia_id',
                    'bloque_horario_id',
                ],
                'horario_actividad_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('horarios')) {
            return;
        }

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropUnique('horario_actividad_unique');
        });

        if (!Schema::hasColumn('horarios', 'grupo')) {
            Schema::table('horarios', function (Blueprint $table) {
                $table->unsignedTinyInteger('grupo')
                    ->nullable()
                    ->after('bloque_horario_id');
            });
        }

        Schema::table('horarios', function (Blueprint $table) {
            $table->unique(
                [
                    'actividad_seccion_id',
                    'version_planificacion_id',
                    'dia_id',
                    'bloque_horario_id',
                    'grupo',
                ],
                'horario_actividad_unique'
            );
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropIndex(
                'horarios_actividad_seccion_id_index'
            );
        });
    }
};