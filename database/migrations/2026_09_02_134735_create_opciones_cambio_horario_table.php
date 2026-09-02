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
        Schema::create('opciones_cambio_horario', function (Blueprint $table) {

            $table->id();

            $table->foreignId('solicitud_cambio_horario_id')
                ->constrained('solicitudes_cambio_horario')
                ->cascadeOnDelete();

            $table->foreignId('dia_id')
                ->constrained('dias')
                ->cascadeOnDelete();

            $table->foreignId('bloque_horario_id')
                ->constrained('bloques_horarios')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('prioridad')->default(1);

            $table->text('observaciones')->nullable();

            $table->string('estado', 30)->default('Propuesta');

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('opciones_cambio_horario');
    }
};