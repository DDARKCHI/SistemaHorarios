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
        Schema::create('respuestas_disponibilidad', function (Blueprint $table) {

            $table->id();

            $table->foreignId('solicitud_disponibilidad_id')
                ->constrained('solicitudes_disponibilidad')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->string('respuesta', 30)->default('Pendiente');

            $table->text('observaciones')->nullable();

            $table->dateTime('fecha_respuesta')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('respuestas_disponibilidad');
    }
};