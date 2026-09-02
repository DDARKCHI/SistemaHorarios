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
        Schema::create('configuracion_institucion', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->string('nombre_sistema', 150)
                ->default('PROSEC');

            $table->string('nombre_institucion', 150)
                ->nullable();

            $table->string('sigla', 30)
                ->nullable();

            $table->string('color_principal', 20)
                ->nullable();

            $table->string('color_secundario', 20)
                ->nullable();

            $table->string('logo', 255)
                ->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_institucion');
    }
};