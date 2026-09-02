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
        Schema::create('disponibilidad_profesores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->foreignId('periodo_academico_id')
                ->constrained('periodos_academicos')
                ->cascadeOnDelete();

            $table->foreignId('dia_id')
                ->constrained('dias')
                ->cascadeOnDelete();

            $table->foreignId('bloque_horario_id')
                ->constrained('bloques_horarios')
                ->cascadeOnDelete();

            $table->string('tipo_disponibilidad', 30)->default('Disponible');

            $table->text('observaciones')->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

            // Evitar registros duplicados de disponibilidad
            $table->unique(
                [
                    'profesor_id',
                    'periodo_academico_id',
                    'dia_id',
                    'bloque_horario_id'
                ],
                'disp_profesor_horario_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_profesores');
    }
};