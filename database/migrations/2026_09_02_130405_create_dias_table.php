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
        Schema::create('dias', function (Blueprint $table) {

            $table->id();
            $table->string('nombre', 20);
            $table->string('abreviatura', 5);
            $table->unsignedTinyInteger('numero');
            $table->boolean('estado')->default(true);
            $table->timestamps();

        });


        // Registrar los días utilizados en la programación académica
        DB::table('dias')->insert([

            [
                'nombre' => 'Lunes',
                'abreviatura' => 'LU',
                'numero' => 1,
                'estado' => true,
            ],

            [
                'nombre' => 'Martes',
                'abreviatura' => 'MA',
                'numero' => 2,
                'estado' => true,
            ],

            [
                'nombre' => 'Miércoles',
                'abreviatura' => 'MI',
                'numero' => 3,
                'estado' => true,
            ],

            [
                'nombre' => 'Jueves',
                'abreviatura' => 'JU',
                'numero' => 4,
                'estado' => true,
            ],

            [
                'nombre' => 'Viernes',
                'abreviatura' => 'VI',
                'numero' => 5,
                'estado' => true,
            ],

            [
                'nombre' => 'Sábado',
                'abreviatura' => 'SA',
                'numero' => 6,
                'estado' => true,
            ],

        ]);
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('dias');
    }
};