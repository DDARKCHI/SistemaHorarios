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
        Schema::create('bloques_horarios', function (Blueprint $table) {

            $table->id();
            $table->unsignedTinyInteger('numero');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('es_almuerzo')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();

        });


        // Registrar los módulos horarios utilizados por la institución
        DB::table('bloques_horarios')->insert([

            [
                'numero' => 1,
                'hora_inicio' => '08:10:00',
                'hora_fin' => '09:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 2,
                'hora_inicio' => '09:10:00',
                'hora_fin' => '10:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 3,
                'hora_inicio' => '10:10:00',
                'hora_fin' => '11:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 4,
                'hora_inicio' => '11:10:00',
                'hora_fin' => '12:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 5,
                'hora_inicio' => '12:10:00',
                'hora_fin' => '13:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 6,
                'hora_inicio' => '13:10:00',
                'hora_fin' => '14:00:00',
                'es_almuerzo' => true,
                'estado' => true,
            ],

            [
                'numero' => 7,
                'hora_inicio' => '14:10:00',
                'hora_fin' => '15:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 8,
                'hora_inicio' => '15:10:00',
                'hora_fin' => '16:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 9,
                'hora_inicio' => '16:10:00',
                'hora_fin' => '17:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 10,
                'hora_inicio' => '17:10:00',
                'hora_fin' => '18:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 11,
                'hora_inicio' => '18:10:00',
                'hora_fin' => '19:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 12,
                'hora_inicio' => '19:10:00',
                'hora_fin' => '20:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 13,
                'hora_inicio' => '20:10:00',
                'hora_fin' => '21:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 14,
                'hora_inicio' => '21:10:00',
                'hora_fin' => '22:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

            [
                'numero' => 15,
                'hora_inicio' => '22:10:00',
                'hora_fin' => '23:00:00',
                'es_almuerzo' => false,
                'estado' => true,
            ],

        ]);
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('bloques_horarios');
    }
};