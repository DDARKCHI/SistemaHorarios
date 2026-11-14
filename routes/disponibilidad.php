<?php

use App\Http\Controllers\DisponibilidadDocenteController as Disponibilidad;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:asignar profesores'])->prefix('disponibilidad')->name('disponibilidad.')->group(function () {
    Route::get('/', [Disponibilidad::class, 'index'])->name('index');
    Route::post('/', [Disponibilidad::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/{solicitud}', [Disponibilidad::class, 'show'])->whereNumber('solicitud')->name('show');
    Route::post('/{solicitud}/revisar', [Disponibilidad::class, 'revisar'])->whereNumber('solicitud')->name('revisar');
    Route::post('/{solicitud}/cancelar', [Disponibilidad::class, 'cancelar'])->whereNumber('solicitud')->name('cancelar');
});

Route::match(['get', 'post'], '/respuesta-disponibilidad/{solicitud}', [Disponibilidad::class, 'publica'])
    ->whereNumber('solicitud')->middleware(['signed', 'throttle:30,1'])->name('disponibilidad.publica');
