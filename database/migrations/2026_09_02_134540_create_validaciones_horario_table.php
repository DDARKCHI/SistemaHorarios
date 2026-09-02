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
        Schema::create('validaciones_horario', function (Blueprint $table) {

            $table->id();

            $table->foreignId('version_planificacion_id')
                ->constrained('versiones_planificacion')
                ->cascadeOnDelete();

            $table->foreignId('horario_id')
                ->nullable()
                ->constrained('horarios')
                ->nullOnDelete();

            $table->string('tipo_validacion', 100);

            $table->string('resultado', 30)
                ->default('Pendiente');

            $table->text('detalle')->nullable();

            $table->string('nivel', 30)
                ->default('Advertencia');

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('validaciones_horario');
    }
};