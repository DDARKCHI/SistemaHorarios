<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {

            $table->id();

            $table->foreignId('universidad_id')
                ->constrained('universidades')
                ->cascadeOnDelete();

            $table->foreignId('facultad_instituto_id')
                ->nullable()
                ->constrained('facultades_institutos')
                ->nullOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnDelete();

            $table->string('codigo', 30)->nullable();
            $table->string('nombre', 150);
            $table->string('regimen', 50)->nullable();
            $table->string('tipo_estudio', 100)->nullable();
            $table->boolean('estado')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};