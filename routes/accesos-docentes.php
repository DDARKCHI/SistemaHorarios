<?php

use App\Http\Controllers\AccesosDocentesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:asignar profesores'])
    ->prefix('disponibilidad/accesos')->name('disponibilidad.accesos.')->group(function () {
        Route::get('/', [AccesosDocentesController::class, 'redirigir'])->name('index');
        Route::post('/enviar', [AccesosDocentesController::class, 'enviar'])
            ->middleware('throttle:6,1')->name('enviar');
    });
