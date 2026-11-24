<?php

use App\Http\Controllers\IdentidadVisualController;
use App\Services\IdentidadVisual;
use Illuminate\Support\Facades\Route;

Route::get('/identidad/archivos/{archivo}', [IdentidadVisualController::class, 'archivo'])->where('archivo', '[a-f0-9]{32}\.png')->name('identidad.archivo');
Route::get('/identidad/logos/{archivo}', [IdentidadVisualController::class, 'logoBase'])
    ->whereIn('archivo', array_values(IdentidadVisual::LOGOS_BASE))->name('identidad.logo-base');
Route::get('/identidad/marca/{tipo}', [IdentidadVisualController::class, 'marca'])->whereIn('tipo', ['principal', 'compacto', 'institucion', 'favicon'])->name('identidad.marca');
Route::get('/identidad/icono.png', [IdentidadVisualController::class, 'icono'])->name('identidad.icono');
Route::get('/identidad/correo/{tipo}.png', [IdentidadVisualController::class, 'logoCorreo'])
    ->withoutMiddleware('web')
    ->whereIn('tipo', array_keys(IdentidadVisual::ANCHOS_LOGOS_CORREO))
    ->name('identidad.logo-correo');
