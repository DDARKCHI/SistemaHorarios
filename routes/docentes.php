<?php
use App\Http\Controllers\DocenteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])->prefix('administracion/docentes')->name('administracion.docentes.')->group(function () {
    Route::get('/', [DocenteController::class, 'index'])->name('index');
    Route::get('/crear', [DocenteController::class, 'create'])->name('create');
    Route::post('/', [DocenteController::class, 'store'])->name('store');
    Route::get('/{profesor}/editar', [DocenteController::class, 'edit'])->whereNumber('profesor')->name('edit');
    Route::put('/{profesor}', [DocenteController::class, 'update'])->whereNumber('profesor')->name('update');
});
