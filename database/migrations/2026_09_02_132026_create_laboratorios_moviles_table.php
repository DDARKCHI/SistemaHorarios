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
        Schema::create('laboratorios_moviles', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnDelete();

            $table->string('codigo', 30);
            $table->string('nombre', 100);

            $table->unsignedSmallInteger('capacidad')->default(0);

            $table->text('descripcion')->nullable();

            $table->boolean('disponible')->default(true);
            $table->boolean('estado')->default(true);

            $table->timestamps();

            $table->unique(['sede_id', 'codigo']);

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratorios_moviles');
    }
};