<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_seccion', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seccion_id')
                ->constrained('secciones')
                ->cascadeOnDelete();

            $table->string('codigo', 20);
            $table->string('nombre')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->unsignedInteger('cupos')->nullable();
            $table->unsignedInteger('inscritos')->default(0);
            $table->string('estado')->default('Activo');
            $table->boolean('estado_activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['seccion_id', 'codigo'],
                'grupo_seccion_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_seccion');
    }
};