<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secciones', function (Blueprint $table) {
            $table->string('identificador_externo')->nullable()->after('id');
            $table->dropForeign(['carrera_id']);
            $table->dropColumn('carrera_id');
        });

        Schema::create('secciones_carreras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seccion_id')
                ->constrained('secciones')
                ->cascadeOnDelete();

            $table->foreignId('carrera_id')
                ->constrained('carreras')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['seccion_id', 'carrera_id'],
                'seccion_carrera_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones_carreras');

        Schema::table('secciones', function (Blueprint $table) {
            $table->foreignId('carrera_id')
                ->nullable()
                ->after('asignatura_id')
                ->constrained('carreras')
                ->nullOnDelete();

            $table->dropColumn('identificador_externo');
        });
    }
};