<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_profesores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete();

            $table->foreignId('profesor_id')
                ->constrained('profesores')
                ->cascadeOnDelete();

            $table->string('tipo_participacion', 50)
                ->nullable();

            $table->boolean('es_principal')
                ->default(false);

            $table->text('observaciones')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->unique(
                ['horario_id', 'profesor_id'],
                'horario_profesor_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_profesores');
    }
};