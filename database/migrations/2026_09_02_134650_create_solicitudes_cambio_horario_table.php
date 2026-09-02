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
        Schema::create('solicitudes_cambio_horario', function (Blueprint $table) {

            $table->id();

            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('motivo', 150);

            $table->text('descripcion')->nullable();

            $table->string('estado', 30)->default('Pendiente');

            $table->dateTime('fecha_solicitud')->nullable();

            $table->dateTime('fecha_respuesta')->nullable();

            $table->text('respuesta')->nullable();

            $table->boolean('estado_activo')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_horario');
    }
};