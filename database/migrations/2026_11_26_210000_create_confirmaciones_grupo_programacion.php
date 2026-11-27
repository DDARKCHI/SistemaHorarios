<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmaciones_grupo_programacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_planificacion_id')->constrained('versiones_planificacion', indexName: 'conf_grupo_version_fk')->cascadeOnDelete();
            $table->foreignId('grupo_seccion_id')->constrained('grupos_seccion', indexName: 'conf_grupo_grupo_fk')->cascadeOnDelete();
            $table->foreignId('profesor_id')->constrained('profesores', indexName: 'conf_grupo_profesor_fk')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users', indexName: 'conf_grupo_usuario_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['version_planificacion_id', 'grupo_seccion_id'], 'confirmacion_grupo_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmaciones_grupo_programacion');
    }
};
