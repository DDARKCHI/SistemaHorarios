<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cierres_programacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_planificacion_id')->constrained('versiones_planificacion')->restrictOnDelete();
            $table->string('etapa', 20);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('firma', 64);
            $table->longText('datos');
            $table->timestamps();
            $table->unique(['version_planificacion_id', 'etapa']);
        });
    }
    public function down(): void { Schema::dropIfExists('cierres_programacion'); }
};
