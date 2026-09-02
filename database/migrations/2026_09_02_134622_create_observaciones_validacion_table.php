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
        Schema::create('observaciones_validacion', function (Blueprint $table) {

            $table->id();

            $table->foreignId('validacion_horario_id')
                ->constrained('validaciones_horario')
                ->cascadeOnDelete();

            $table->foreignId('horario_id')
                ->nullable()
                ->constrained('horarios')
                ->nullOnDelete();

            $table->string('tipo', 100);

            $table->string('nivel', 30)
                ->default('Advertencia');

            $table->text('mensaje');

            $table->boolean('resuelta')->default(false);

            $table->dateTime('fecha_resolucion')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('observaciones_validacion');
    }
};