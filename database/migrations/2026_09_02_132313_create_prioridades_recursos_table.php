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
        Schema::create('prioridades_recursos', function (Blueprint $table) {

            $table->id();

            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();

            $table->unsignedTinyInteger('nivel')->default(1);

            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('prioridades_recursos');
    }
};