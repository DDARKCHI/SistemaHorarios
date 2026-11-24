<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->foreignId('profesor_id')->nullable()->unique()->constrained('profesores')->nullOnDelete();
            $table->boolean('debe_cambiar_password')->default(false);
            $table->text('clave_temporal')->nullable();
            $table->timestamp('primer_ingreso_en')->nullable();
            $table->timestamp('ultimo_ingreso_en')->nullable();
        });

        Schema::create('envios_accesos_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('periodo_academico_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('solicitud_disponibilidad_id')->nullable()->constrained('solicitudes_disponibilidad')->nullOnDelete();
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('lote');
            $table->string('estado', 20)->default('pendiente');
            $table->string('correo')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['lote', 'profesor_id']);
            $table->index(['periodo_academico_id', 'profesor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_accesos_docentes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profesor_id']);
            $table->dropUnique(['profesor_id']);
            $table->dropColumn(['profesor_id', 'debe_cambiar_password', 'clave_temporal', 'primer_ingreso_en', 'ultimo_ingreso_en']);
        });
    }
};
