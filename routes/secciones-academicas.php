<?php
use App\Http\Controllers\SeccionAcademicaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission.redirect:administrar usuarios'])->prefix('administracion/secciones')->name('administracion.secciones.')->group(function () {
    Route::get('/', [SeccionAcademicaController::class, 'index'])->name('index');
    Route::get('/crear', [SeccionAcademicaController::class, 'create'])->name('create');
    Route::post('/', [SeccionAcademicaController::class, 'store'])->name('store');
    Route::post('/iniciar-borrador', [SeccionAcademicaController::class, 'iniciar'])->middleware('permission.redirect:generar programacion semestral')->name('iniciar');
    Route::get('/{seccion}', [SeccionAcademicaController::class, 'show'])->whereNumber('seccion')->name('show');
    Route::get('/{seccion}/editar', [SeccionAcademicaController::class, 'edit'])->whereNumber('seccion')->name('edit');
    Route::put('/{seccion}', [SeccionAcademicaController::class, 'update'])->whereNumber('seccion')->name('update');
    Route::prefix('/{seccion}/actividades')->whereNumber('seccion')->name('actividad.')->group(function () {
        Route::get('/crear', [SeccionAcademicaController::class, 'actividad'])->name('create');
        Route::post('/', [SeccionAcademicaController::class, 'guardarActividad'])->name('store');
        Route::get('/{id}/editar', [SeccionAcademicaController::class, 'actividad'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [SeccionAcademicaController::class, 'guardarActividad'])->whereNumber('id')->name('update');
    });
    Route::prefix('/{seccion}/actividades/{actividad}/grupos')->whereNumber(['seccion', 'actividad'])->name('grupo.')->group(function () {
        Route::get('/crear', [SeccionAcademicaController::class, 'grupo'])->name('create');
        Route::post('/', [SeccionAcademicaController::class, 'guardarGrupo'])->name('store');
        Route::get('/{id}/editar', [SeccionAcademicaController::class, 'grupo'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [SeccionAcademicaController::class, 'guardarGrupo'])->whereNumber('id')->name('update');
    });
});
