<?php

use App\Http\Controllers\CatalogoAcademicoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])
    ->prefix('administracion/academicos/{tipo}')->whereIn('tipo', ['facultades', 'planes', 'niveles'])
    ->name('administracion.academicos.')->group(function () {
        Route::get('/', [CatalogoAcademicoController::class, 'index'])->name('index');
        Route::get('/crear', [CatalogoAcademicoController::class, 'create'])->name('create');
        Route::post('/', [CatalogoAcademicoController::class, 'store'])->name('store');
        Route::get('/{id}/editar', [CatalogoAcademicoController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [CatalogoAcademicoController::class, 'update'])->whereNumber('id')->name('update');
    });
