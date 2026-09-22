<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ImportacionProgramacionController;
use App\Http\Controllers\VisualizacionProgramacionController;
use App\Http\Controllers\ProgramacionSemestralController;
use App\Http\Controllers\IntercambioDocenteController;
use App\Imports\PruebaImport;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/login', [LoginController::class, 'mostrarLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'iniciarSesion'])
    ->name('login.procesar');

Route::post('/logout', [LoginController::class, 'cerrarSesion'])
    ->name('logout');

Route::get('/', function () {
    return view('inicio');
})->middleware('auth')->name('inicio');

Route::middleware('auth')->group(function () {

    Route::get(
        '/programacion/semestral',
        [ProgramacionSemestralController::class, 'index']
    )->name('programacion.semestral');

    Route::get(
        '/programacion/semestral/filtros',
        [ProgramacionSemestralController::class, 'filtros']
    )->name('programacion.semestral.filtros');

    Route::get(
        '/programacion',
        [VisualizacionProgramacionController::class, 'index']
    )->name('programacion.index');

    Route::get(
        '/programacion/filtros',
        [VisualizacionProgramacionController::class, 'filtros']
    )->name('programacion.filtros');

    Route::get(
        '/programacion/importar',
        [ImportacionProgramacionController::class, 'index']
    )->name('programacion.importar');

    Route::post(
        '/programacion/importar/analizar',
        [ImportacionProgramacionController::class, 'analizar']
    )->name('programacion.importar.analizar');

    Route::post(
        '/programacion/importar/confirmar',
        [ImportacionProgramacionController::class, 'confirmar']
    )->name('programacion.importar.confirmar');

    Route::post(
        '/programacion/importar/cancelar',
        [ImportacionProgramacionController::class, 'cancelar']
    )->name('programacion.importar.cancelar');

    Route::get(
        '/programacion/intercambio',
        [IntercambioDocenteController::class, 'index']
    )->name('programacion.intercambio');

    Route::get('/salas', function () {
        return view('salas.index');
    })->name('salas.index');

    Route::get('/administracion', function () {
        return view('administracion.index');
    })->name('administracion.index');

    Route::get('/configuracion', function () {
        return view('configuracion.index');
    })->name('configuracion.index');
});

Route::get('/prueba-excel', function () {
    $archivo = storage_path('app/prueba.xlsx');

    $datos = Excel::toArray(new PruebaImport, $archivo);

    return response()->json([
        'hojas' => count($datos),
        'filas_primera_hoja' => count($datos[0] ?? []),
        'primeras_filas' => array_slice($datos[0] ?? [], 0, 5),
    ]);
});