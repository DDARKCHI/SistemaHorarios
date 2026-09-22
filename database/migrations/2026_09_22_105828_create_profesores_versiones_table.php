<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesores_versiones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('version_planificacion_id')
                ->constrained('versiones_planificacion')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->restrictOnDelete();

            $table->string('rut')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('correo_institucional')->nullable();
            $table->string('correo_personal')->nullable();
            $table->string('tipo_contrato')->nullable();
            $table->string('tipo_profesor')->nullable();
            $table->string('unidad_academica')->nullable();

            $table->timestamps();

            $table->unique(
                ['version_planificacion_id', 'profesor_id'],
                'profesor_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesores_versiones');
    }
};