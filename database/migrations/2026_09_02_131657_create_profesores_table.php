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
        Schema::create('profesores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->string('rut', 12)->nullable();
            $table->string('nombre', 100);
            $table->string('apellido', 100);

            $table->string('correo_institucional', 150)->nullable();
            $table->string('correo_personal', 150)->nullable();

            $table->string('tipo_contrato', 50)->nullable();
            $table->string('tipo_profesor', 50)->nullable();

            $table->string('unidad_academica', 150)->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('profesores');
    }
};