<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_disponibilidad_id')
                ->constrained('solicitudes_disponibilidad', 'id', 'recordatorios_solicitud_foreign')->cascadeOnDelete();
            $table->foreignId('envio_acceso_docente_id')->nullable()
                ->constrained('envios_accesos_docentes', 'id', 'recordatorios_envio_foreign')->nullOnDelete();
            $table->unsignedTinyInteger('dias_antes');
            $table->date('enviar_el');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('enviado_en')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['solicitud_disponibilidad_id', 'dias_antes'], 'recordatorios_solicitud_hito_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios_disponibilidad');
    }
};
