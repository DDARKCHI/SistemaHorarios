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
        Schema::create('temas_visuales', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->string('nombre', 100);

            $table->string('color_principal', 20)->nullable();
            $table->string('color_secundario', 20)->nullable();
            $table->string('color_fondo', 20)->nullable();
            $table->string('color_texto', 20)->nullable();

            $table->string('logo_principal', 255)->nullable();
            $table->string('logo_secundario', 255)->nullable();

            $table->boolean('es_principal')->default(false);
            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('temas_visuales');
    }
};