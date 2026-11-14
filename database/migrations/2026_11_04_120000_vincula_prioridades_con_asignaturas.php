<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prioridades_recursos', function (Blueprint $table) {
            $table->foreignId('asignatura_id')->nullable()->constrained('asignaturas')->restrictOnDelete();
            $table->string('tipo_recurso', 20)->nullable();
            $table->foreignId('tipo_sala_id')->nullable()->constrained('tipos_sala')->restrictOnDelete();
            $table->string('tipo_equipamiento', 100)->nullable();
            $table->string('clave_recurso', 191)->nullable();
            $table->unique(['asignatura_id', 'clave_recurso'], 'prioridad_asignatura_recurso_unique');
        });
    }

    public function down(): void
    {
        Schema::table('prioridades_recursos', function (Blueprint $table) {
            $table->dropUnique('prioridad_asignatura_recurso_unique');
            $table->dropConstrainedForeignId('asignatura_id');
            $table->dropConstrainedForeignId('tipo_sala_id');
            $table->dropColumn(['tipo_recurso', 'tipo_equipamiento', 'clave_recurso']);
        });
    }
};
