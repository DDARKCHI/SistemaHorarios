<?php
use App\Http\Controllers\CompletarProgramacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:generar programacion semestral'])->prefix('programacion/completar')->name('programacion.completar.')->group(function () {
    Route::get('/', [CompletarProgramacionController::class, 'index'])->name('index');
    Route::post('/', [CompletarProgramacionController::class, 'generar'])->name('generar');
    Route::get('/propuesta/{token}', [CompletarProgramacionController::class, 'show'])->whereUuid('token')->name('show');
    Route::post('/propuesta/{token}', [CompletarProgramacionController::class, 'guardar'])->whereUuid('token')->name('guardar');
});
