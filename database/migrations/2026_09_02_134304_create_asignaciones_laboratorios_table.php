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
        Schema::create('asignaciones_laboratorios', function (Blueprint $table) {

            $table->id();

            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete();

            $table->foreignId('laboratorio_id')
                ->constrained('laboratorios')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('capacidad_asignada')->nullable();

            $table->string('observaciones', 255)->nullable();

            $table->boolean('es_principal')->default(true);
            $table->boolean('estado')->default(true);

            $table->timestamps();

            // Evitar asignar el mismo laboratorio dos veces al mismo horario
            $table->unique(
                ['horario_id', 'laboratorio_id'],
                'horario_laboratorio_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_laboratorios');
    }
};