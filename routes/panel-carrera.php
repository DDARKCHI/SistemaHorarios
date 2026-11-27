<?php

use App\Http\Controllers\PanelCarreraController as Panel;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('programacion/panel-carrera')->name('programacion.panel-carrera')->group(function () {
    Route::get('/', [Panel::class, 'index']);
    Route::get('/excel', [Panel::class, 'exportar'])->name('.exportar');
    Route::get('/horarios/{horario}/destinos', [Panel::class, 'evaluar'])->whereNumber('horario')->name('.evaluar');
    Route::patch('/horarios/{horario}', [Panel::class, 'mover'])->whereNumber('horario')->name('.mover');
    Route::delete('/horarios/{horario}', [Panel::class, 'quitar'])->whereNumber('horario')->name('.quitar');
    Route::post('/horarios/{horario}/espacio', [Panel::class, 'espacio'])->whereNumber('horario')->name('.espacio');
    Route::post('/grupos/{grupo}/horarios', [Panel::class, 'agregar'])->whereNumber('grupo')->name('.agregar');
    Route::post('/grupos/{grupo}/docente', [Panel::class, 'asignar'])->whereNumber('grupo')->name('.asignar');
    Route::post('/grupos/{grupo}/confirmacion', [Panel::class, 'confirmar'])->whereNumber('grupo')->name('.confirmar');
});
