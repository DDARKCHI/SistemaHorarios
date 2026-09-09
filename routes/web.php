<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ImportacionProgramacionController;
use App\Http\Controllers\VisualizacionProgramacionController;
use App\Imports\PruebaImport;
use Maatwebsite\Excel\Facades\Excel;

// Login
Route::get('/login', [LoginController::class, 'mostrarLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'iniciarSesion'])
    ->name('login.procesar');

// Cerrar sesión
Route::post('/logout', [LoginController::class, 'cerrarSesion'])
    ->name('logout');

// Inicio
Route::get('/', function () {
    return view('inicio');
})->middleware('auth')->name('inicio');

// Programación
Route::middleware('auth')->group(function () {

    // Visualización de programación
    Route::get(
        '/programacion',
        [VisualizacionProgramacionController::class, 'index']
    )->name('programacion.index');

    // Actualización dinámica de filtros
    Route::get(
        '/programacion/filtros',
        [VisualizacionProgramacionController::class, 'filtros']
    )->name('programacion.filtros');

    // Cargar programación anterior
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
});

// Prueba temporal de Laravel Excel
Route::get('/prueba-excel', function () {
    $archivo = storage_path('app/prueba.xlsx');

    $datos = Excel::toArray(new PruebaImport, $archivo);

    return response()->json([
        'hojas' => count($datos),
        'filas_primera_hoja' => count($datos[0] ?? []),
        'primeras_filas' => array_slice($datos[0] ?? [], 0, 5),
    ]);
});