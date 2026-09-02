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
        Schema::create('versiones_planificacion', function (Blueprint $table) {

            $table->id();

            $table->foreignId('periodo_academico_id')
                ->constrained('periodos_academicos')
                ->cascadeOnDelete();

            $table->string('nombre', 100);

            $table->string('version', 30)->default('1.0');

            $table->text('descripcion')->nullable();

            $table->string('estado', 30)->default('Borrador');

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('fecha_publicacion')->nullable();

            $table->boolean('es_actual')->default(false);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('versiones_planificacion');
    }
};