<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('respuestas_disponibilidad', function (Blueprint $table) {
            $table->json('bloques')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->text('motivo_revision')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('respuestas_disponibilidad', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropColumn(['bloques', 'revisado_por', 'revisado_en', 'motivo_revision']);
        });
    }
};
