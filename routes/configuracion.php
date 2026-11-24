<?php
use App\Http\Controllers\ConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:ver configuracion'])->prefix('configuracion')->name('configuracion.')->group(function () {
    Route::get('/identidad', [\App\Http\Controllers\IdentidadVisualController::class, 'edit'])->name('identidad');
    Route::get('/calendario', [ConfiguracionController::class, 'calendario'])->name('calendario');
    Route::middleware('permission.redirect:administrar usuarios')->group(function () {
        Route::post('/identidad/restaurar', [\App\Http\Controllers\IdentidadVisualController::class, 'restaurar'])->name('identidad.restaurar');
        Route::post('/identidad/{universidad}', [\App\Http\Controllers\IdentidadVisualController::class, 'update'])->whereNumber('universidad')->name('identidad.update');
        Route::post('/instituciones', [ConfiguracionController::class, 'guardarInstitucion'])->name('institucion.store');
        Route::put('/instituciones/{id}', [ConfiguracionController::class, 'guardarInstitucion'])->whereNumber('id')->name('institucion.update');
        Route::prefix('/calendario/{tipo}')->whereIn('tipo', ['dias', 'bloques'])->name('calendario.')->group(function () {
            Route::get('/crear', [ConfiguracionController::class, 'editarCalendario'])->name('create');
            Route::get('/{id}/editar', [ConfiguracionController::class, 'editarCalendario'])->whereNumber('id')->name('edit');
            Route::post('/', [ConfiguracionController::class, 'guardarCalendario'])->name('store');
            Route::put('/{id}', [ConfiguracionController::class, 'guardarCalendario'])->whereNumber('id')->name('update');
        });
    });
});
