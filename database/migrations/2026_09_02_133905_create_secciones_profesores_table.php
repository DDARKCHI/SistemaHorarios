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
        Schema::create('secciones_profesores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seccion_id')
                ->constrained('secciones')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->string('tipo_participacion', 50)
                ->default('Profesor responsable');

            $table->boolean('es_principal')->default(false);

            $table->text('observaciones')->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

            // Evitar duplicar un profesor en la misma sección
            $table->unique(
                ['seccion_id', 'profesor_id'],
                'seccion_profesor_unique'
            );

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('secciones_profesores');
    }
};