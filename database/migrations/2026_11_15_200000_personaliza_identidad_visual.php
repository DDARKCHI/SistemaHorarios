<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_institucion', function (Blueprint $table) {
            $table->boolean('identidad_activa')->default(false)->index();
            $table->string('lema_sistema', 150)->nullable();
            $table->string('color_fondo', 20)->nullable();
            $table->string('logo_compacto', 255)->nullable();
            $table->string('logo_institucion', 255)->nullable();
            $table->string('favicon', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_institucion', function (Blueprint $table) {
            $table->dropIndex(['identidad_activa']);
            $table->dropColumn(['identidad_activa', 'lema_sistema', 'color_fondo', 'logo_compacto', 'logo_institucion', 'favicon']);
        });
    }
};
