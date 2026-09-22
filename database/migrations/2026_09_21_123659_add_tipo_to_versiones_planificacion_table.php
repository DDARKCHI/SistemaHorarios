<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_planificacion', function (Blueprint $table) {
            $table->string('tipo', 20)
                ->default('anterior')
                ->after('version');
        });

        DB::table('versiones_planificacion')
            ->update([
                'tipo' => 'anterior',
            ]);
    }

    public function down(): void
    {
        Schema::table('versiones_planificacion', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};