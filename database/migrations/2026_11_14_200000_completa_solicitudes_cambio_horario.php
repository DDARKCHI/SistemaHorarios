<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_cambio_horario', function (Blueprint $table) {
            $table->timestamp('fecha_limite')->nullable();
            $table->char('firma_origen', 64)->nullable();
            $table->json('origen')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->text('motivo_revision')->nullable();
        });
        Schema::table('opciones_cambio_horario', fn (Blueprint $table) => $table->json('datos')->nullable());
        Schema::create('respuestas_solicitud_cambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_horario_id')->constrained('solicitudes_cambio_horario')->cascadeOnDelete();
            $table->foreignId('profesor_id')->constrained('profesores')->restrictOnDelete();
            $table->foreignId('opcion_id')->nullable()->constrained('opciones_cambio_horario')->nullOnDelete();
            $table->string('estado', 30)->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamp('respondido_en')->nullable();
            $table->timestamps();
            $table->unique(['solicitud_cambio_horario_id', 'profesor_id'], 'respuesta_cambio_docente_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('respuestas_solicitud_cambio');
        Schema::table('opciones_cambio_horario', fn (Blueprint $table) => $table->dropColumn('datos'));
        Schema::table('solicitudes_cambio_horario', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropColumn(['fecha_limite', 'firma_origen', 'origen', 'revisado_por', 'revisado_en', 'motivo_revision']);
        });
    }
};
