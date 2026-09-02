<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::create('universidades', function (Blueprint $table) {

            $table->id();
            $table->string('nombre', 150);
            $table->string('sigla', 20);
            $table->boolean('estado')->default(true);
            $table->timestamps();

        });


        // Registrar la institución inicial del sistema
        DB::table('universidades')->insert([

            [
                'nombre' => 'Universidad Católica de la Santísima Concepción',
                'sigla' => 'UCSC',
                'estado' => true,
            ],

        ]);
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('universidades');
    }
};