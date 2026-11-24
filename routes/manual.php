<?php
use App\Http\Controllers\ManualUsuarioController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth')->prefix('ayuda')->name('ayuda.')->group(function () {
    Route::get('/manual', [ManualUsuarioController::class, 'index'])->name('manual');
    Route::get('/manual.pdf', [ManualUsuarioController::class, 'pdf'])->name('pdf');
    Route::get('/manual/leer.pdf', [ManualUsuarioController::class, 'verPdf'])->name('pdf.ver');
});
