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
        Schema::create('actividades_seccion', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seccion_id')
                ->constrained('secciones')
                ->cascadeOnDelete();

            $table->string('tipo', 50);

            $table->unsignedTinyInteger('cantidad_horas')->default(1);

            $table->unsignedSmallInteger('cantidad_estudiantes')->nullable();

            $table->string('modalidad', 50)->nullable();

            $table->text('observaciones')->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades_seccion');
    }
};