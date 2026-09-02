<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {

            $table->id();

            $table->foreignId('actividad_seccion_id')
                ->constrained('actividades_seccion')
                ->cascadeOnDelete();

            $table->foreignId('version_planificacion_id')
                ->constrained('versiones_planificacion')
                ->cascadeOnDelete();

            $table->foreignId('dia_id')
                ->constrained('dias')
                ->cascadeOnDelete();

            $table->foreignId('bloque_horario_id')
                ->constrained('bloques_horarios')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('grupo')->default(1);

            $table->string('observaciones', 255)->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

            // Evitar duplicar una actividad en el mismo día y módulo
            $table->unique(
                [
                    'actividad_seccion_id',
                    'version_planificacion_id',
                    'dia_id',
                    'bloque_horario_id',
                    'grupo'
                ],
                'horario_actividad_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};