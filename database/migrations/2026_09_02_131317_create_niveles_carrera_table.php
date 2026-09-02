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
        Schema::create('niveles_carrera', function (Blueprint $table) {

            $table->id();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('numero');
            $table->string('nombre', 100);
            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('niveles_carrera');
    }
};