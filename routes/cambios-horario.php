<?php

use App\Http\Controllers\SolicitudCambioHorarioController as Cambios;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:asignar horarios'])->prefix('programacion/solicitudes-cambio')->name('programacion.cambios.')->group(function () {
    Route::get('/', [Cambios::class, 'index'])->name('index');
    Route::get('/horario/{horario}', [Cambios::class, 'create'])->whereNumber('horario')->name('create');
    Route::post('/horario/{horario}', [Cambios::class, 'store'])->whereNumber('horario')->middleware('throttle:20,1')->name('store');
    Route::get('/{solicitud}', [Cambios::class, 'show'])->whereNumber('solicitud')->name('show');
    Route::post('/{solicitud}/revisar', [Cambios::class, 'revisar'])->whereNumber('solicitud')->name('revisar');
});
Route::match(['get', 'post'], '/respuesta-cambio/{respuesta}', [Cambios::class, 'responder'])->whereNumber('respuesta')
    ->middleware(['signed', 'throttle:30,1'])->name('programacion.cambios.responder');
