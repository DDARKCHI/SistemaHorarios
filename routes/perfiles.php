<?php

use App\Http\Controllers\PerfilController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])->prefix('administracion/perfiles')->name('administracion.perfiles.')->group(function () {
    Route::get('/', [PerfilController::class, 'index'])->name('index');
    Route::get('/crear', [PerfilController::class, 'create'])->name('create');
    Route::post('/', [PerfilController::class, 'store'])->name('store');
    Route::get('/{perfil}/editar', [PerfilController::class, 'edit'])->whereNumber('perfil')->name('edit');
    Route::put('/{perfil}', [PerfilController::class, 'update'])->whereNumber('perfil')->name('update');
});
