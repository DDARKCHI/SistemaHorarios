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
        Schema::create('cambios_importacion_profesores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('importacion_profesores_id')
                ->constrained('importaciones_profesores')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->nullable()
                ->constrained('profesores')
                ->nullOnDelete();

            $table->string('tipo_cambio', 50);

            $table->string('campo', 100)->nullable();

            $table->text('valor_anterior')->nullable();

            $table->text('valor_nuevo')->nullable();

            $table->text('descripcion')->nullable();

            $table->string('estado', 30)->default('Pendiente');

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('cambios_importacion_profesores');
    }
};