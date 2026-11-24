<?php

use App\Http\Controllers\MiDisponibilidadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('mi-disponibilidad')->name('mi.disponibilidad.')->group(function () {
        Route::get('/', [MiDisponibilidadController::class, 'index'])->name('index');
        Route::get('/{solicitud}', [MiDisponibilidadController::class, 'show'])->whereNumber('solicitud')->name('show');
        Route::post('/{solicitud}', [MiDisponibilidadController::class, 'guardar'])
            ->whereNumber('solicitud')->middleware('throttle:10,1')->name('guardar');
    });
