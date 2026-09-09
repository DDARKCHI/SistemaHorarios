<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras_sedes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['carrera_id', 'sede_id'],
                'carrera_sede_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras_sedes');
    }
};