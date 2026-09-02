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
        Schema::create('planes_estudio', function (Blueprint $table) {

            $table->id();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete();

            $table->string('codigo', 30)->nullable();
            $table->string('nombre', 150)->nullable();
            $table->string('version', 30)->nullable();
            $table->unsignedSmallInteger('anio_inicio')->nullable();
            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_estudio');
    }
};