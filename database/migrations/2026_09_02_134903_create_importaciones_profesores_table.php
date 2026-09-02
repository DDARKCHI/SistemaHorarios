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
        Schema::create('importaciones_profesores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('nombre_archivo', 255);

            $table->string('ruta_archivo', 500)->nullable();

            $table->unsignedInteger('total_registros')->default(0);
            $table->unsignedInteger('registros_nuevos')->default(0);
            $table->unsignedInteger('registros_actualizados')->default(0);
            $table->unsignedInteger('registros_sin_cambios')->default(0);
            $table->unsignedInteger('registros_con_error')->default(0);

            $table->string('estado', 30)->default('Pendiente');

            $table->text('observaciones')->nullable();

            $table->dateTime('fecha_importacion')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_profesores');
    }
};