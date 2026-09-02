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
        Schema::create('asignaturas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->string('codigo', 30);
            $table->string('nombre', 200);

            $table->unsignedTinyInteger('creditos')->nullable();
            $table->unsignedTinyInteger('horas_catedra')->default(0);
            $table->unsignedTinyInteger('horas_laboratorio')->default(0);
            $table->unsignedTinyInteger('horas_ayudantia')->default(0);
            $table->unsignedTinyInteger('horas_taller')->default(0);
            $table->unsignedTinyInteger('horas_clinica')->default(0);
            $table->unsignedTinyInteger('horas_practica')->default(0);
            $table->unsignedTinyInteger('horas_otros')->default(0);

            $table->string('modalidad', 50)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->string('tipo_dictacion', 50)->nullable();

            $table->boolean('es_libre_eleccion')->default(false);
            $table->boolean('ramo_terminal')->default(false);
            $table->boolean('estado')->default(true);

            $table->timestamps();

            $table->unique(['universidad_id', 'codigo']);

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};