<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_profesores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grupo_seccion_id')
                ->constrained('grupos_seccion')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->string('tipo_participacion', 50)
                ->default('PROFESOR RESPONSABLE');

            $table->boolean('es_principal')
                ->default(false);

            $table->text('observaciones')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->unique(
                ['grupo_seccion_id', 'profesor_id'],
                'grupo_profesor_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_profesores');
    }
};