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
        Schema::create('historial_acciones', function (Blueprint $table) {

            $table->id();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('accion', 100);

            $table->string('modulo', 100)->nullable();

            $table->string('tabla_afectada', 100)->nullable();

            $table->unsignedBigInteger('registro_id')->nullable();

            $table->text('descripcion')->nullable();

            $table->json('datos')->nullable();

            $table->string('direccion_ip', 45)->nullable();

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_acciones');
    }
};