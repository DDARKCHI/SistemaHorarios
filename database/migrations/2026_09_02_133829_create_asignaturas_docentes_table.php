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
        Schema::create('asignaturas_docentes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->foreignId('periodo_academico_id')
                ->constrained('periodos_academicos')
                ->cascadeOnDelete();

            $table->string('tipo_participacion', 50)
                ->default('Profesor responsable');

            $table->boolean('es_principal')->default(false);
            $table->boolean('estado')->default(true);

            $table->timestamps();

            // Evitar duplicar la misma relación entre profesor y asignatura
            $table->unique(
                [
                    'asignatura_id',
                    'profesor_id',
                    'periodo_academico_id'
                ],
                'asig_prof_periodo_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaturas_docentes');
    }
};