<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamientos_espacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipamiento_id')->constrained('equipamientos')->restrictOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('salas')->restrictOnDelete();
            $table->foreignId('laboratorio_movil_id')->nullable()->constrained('laboratorios_moviles')->restrictOnDelete();
            $table->unsignedSmallInteger('cantidad');
            $table->timestamps();
            $table->unique(['equipamiento_id', 'sala_id'], 'equip_espacio_sala_unique');
            $table->unique(['equipamiento_id', 'laboratorio_movil_id'], 'equip_espacio_movil_unique');
        });
        Schema::table('requerimientos_actividad', function (Blueprint $table) {
            $table->foreignId('equipamiento_id')->nullable()->constrained('equipamientos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requerimientos_actividad', fn (Blueprint $table) => $table->dropConstrainedForeignId('equipamiento_id'));
        Schema::dropIfExists('equipamientos_espacios');
    }
};
