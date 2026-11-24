<?php
use App\Http\Controllers\OfertaAcademicaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])->prefix('administracion/oferta/{tipo}')
    ->whereIn('tipo', ['asignaturas', 'periodos'])->name('administracion.oferta.')->group(function () {
        Route::get('/', [OfertaAcademicaController::class, 'index'])->name('index');
        Route::get('/crear', [OfertaAcademicaController::class, 'create'])->name('create');
        Route::post('/', [OfertaAcademicaController::class, 'store'])->name('store');
        Route::get('/{id}/editar', [OfertaAcademicaController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [OfertaAcademicaController::class, 'update'])->whereNumber('id')->name('update');
    });
