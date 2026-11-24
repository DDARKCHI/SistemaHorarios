<?php
use App\Http\Controllers\EstructuraInstitucionalController as Estructura;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])->prefix('administracion/estructura/{tipo}')->whereIn('tipo', ['sedes', 'carreras'])->name('administracion.estructura.')->group(function () {
    Route::get('/', [Estructura::class, 'index'])->name('index');
    Route::get('/crear', [Estructura::class, 'create'])->name('create');
    Route::post('/', [Estructura::class, 'store'])->name('store');
    Route::get('/{id}/editar', [Estructura::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}', [Estructura::class, 'update'])->whereNumber('id')->name('update');
});
