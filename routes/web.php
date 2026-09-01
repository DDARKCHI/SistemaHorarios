<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;


// Login
Route::get('/login', [LoginController::class, 'mostrarLogin'])->name('login');

Route::post('/login', [LoginController::class, 'iniciarSesion'])->name('login.procesar');


// Cerrar sesión
Route::post('/logout', [LoginController::class, 'cerrarSesion'])->name('logout');


// Inicio
Route::get('/', function () {
    return view('inicio');
})->middleware('auth')->name('inicio');