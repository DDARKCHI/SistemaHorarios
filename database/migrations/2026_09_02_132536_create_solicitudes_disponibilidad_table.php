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
        Schema::create('solicitudes_disponibilidad', function (Blueprint $table) {

            $table->id();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->foreignId('periodo_academico_id')
                ->constrained('periodos_academicos')
                ->cascadeOnDelete();

            $table->date('fecha_solicitud')->nullable();
            $table->date('fecha_limite')->nullable();

            $table->text('observaciones')->nullable();

            $table->string('estado', 30)->default('Pendiente');

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_disponibilidad');
    }
};