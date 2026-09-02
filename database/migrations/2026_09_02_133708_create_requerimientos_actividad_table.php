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
        Schema::create('requerimientos_actividad', function (Blueprint $table) {

            $table->id();

            $table->foreignId('actividad_seccion_id')
                ->constrained('actividades_seccion')
                ->cascadeOnDelete();

            $table->foreignId('tipo_sala_id')
                ->nullable()
                ->constrained('tipos_sala')
                ->nullOnDelete();

            $table->foreignId('prioridad_recurso_id')
                ->nullable()
                ->constrained('prioridades_recursos')
                ->nullOnDelete();

            $table->string('tipo_requerimiento', 50);

            $table->string('nombre_recurso', 100)->nullable();

            $table->unsignedSmallInteger('cantidad')->default(1);

            $table->unsignedSmallInteger('capacidad_minima')->nullable();

            $table->text('observaciones')->nullable();

            $table->boolean('obligatorio')->default(false);

            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerimientos_actividad');
    }
};