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
        Schema::create('equipamientos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->string('nombre', 100);
            $table->string('tipo', 100);
            $table->string('descripcion', 255)->nullable();

            $table->unsignedSmallInteger('cantidad')->default(1);

            $table->boolean('disponible')->default(true);
            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipamientos');
    }
};