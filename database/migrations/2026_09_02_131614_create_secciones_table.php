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
        Schema::create('secciones', function (Blueprint $table) {

            $table->id();

            $table->foreignId('asignatura_id')
                ->constrained('asignaturas')
                ->cascadeOnDelete();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete();

            $table->foreignId('periodo_academico_id')
                ->constrained('periodos_academicos')
                ->cascadeOnDelete();

            $table->string('codigo', 30)->nullable();
            $table->string('nombre', 100)->nullable();

            $table->unsignedSmallInteger('cupos')->nullable();
            $table->unsignedSmallInteger('inscritos')->default(0);

            $table->string('modalidad', 50)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->string('tipo_dictacion', 50)->nullable();

            $table->string('estado', 30)->default('Pendiente');

            $table->boolean('estado_activo')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('secciones');
    }
};