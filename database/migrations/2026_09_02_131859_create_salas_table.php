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
        Schema::create('salas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnDelete();

            $table->foreignId('tipo_sala_id')
                ->constrained('tipos_sala')
                ->cascadeOnDelete();

            $table->string('codigo', 30);
            $table->string('nombre', 100)->nullable();

            $table->unsignedSmallInteger('capacidad')->default(0);

            $table->string('edificio', 100)->nullable();
            $table->string('piso', 30)->nullable();

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
        Schema::dropIfExists('salas');
    }
};