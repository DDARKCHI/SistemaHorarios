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
        Schema::create('candidatos_reemplazo', function (Blueprint $table) {

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

            $table->string('motivo', 100)->nullable();

            $table->unsignedTinyInteger('nivel_compatibilidad')
                ->nullable();

            $table->text('observaciones')->nullable();

            $table->string('estado', 30)->default('Pendiente');

            $table->boolean('estado_activo')->default(true);

            $table->timestamps();

            // Evitar registrar dos veces al mismo candidato
            $table->unique(
                [
                    'asignatura_id',
                    'profesor_id',
                    'periodo_academico_id'
                ],
                'candidato_asig_periodo_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidatos_reemplazo');
    }
};