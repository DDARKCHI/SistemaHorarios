<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\ImportacionProgramacionController;
use App\Http\Controllers\VisualizacionProgramacionController;
use App\Http\Controllers\ProgramacionSemestralController;
use App\Http\Controllers\IntercambioDocenteController;
use App\Http\Controllers\PropuestaHorarioController;
use App\Http\Controllers\AsignacionProfesorController;
use App\Http\Controllers\AsignacionHorarioController;
use App\Http\Controllers\RecuperarContrasenaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\HorarioEspacioController;
use App\Imports\PruebaImport;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/login', [LoginController::class, 'mostrarLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'iniciarSesion'])
    ->name('login.procesar');

Route::post('/logout', [LoginController::class, 'cerrarSesion'])
    ->name('logout');

Route::get(
    '/recuperar-contrasena',
    [RecuperarContrasenaController::class, 'mostrarSolicitud']
)->name('password.request');

Route::post(
    '/recuperar-contrasena',
    [RecuperarContrasenaController::class, 'enviarEnlace']
)->name('password.email');

Route::get(
    '/restablecer-contrasena/{token}',
    [RecuperarContrasenaController::class, 'mostrarRestablecimiento']
)->name('password.reset');

Route::post(
    '/restablecer-contrasena',
    [RecuperarContrasenaController::class, 'restablecer']
)->name('password.update');

Route::get('/', [InicioController::class, 'index'])
    ->middleware([
        'auth',
        'permission.redirect:ver panel inicio',
    ])
    ->name('inicio');

Route::middleware('auth')->group(function () {

    Route::get(
        '/programacion/semestral',
        [ProgramacionSemestralController::class, 'index']
    )
        ->middleware(
            'permission.redirect:ver programacion semestral'
        )
        ->name('programacion.semestral');

    Route::post(
        '/programacion/semestral/generar',
        [ProgramacionSemestralController::class, 'generar']
    )
        ->middleware(
            'permission.redirect:generar programacion semestral'
        )
        ->name('programacion.semestral.generar');

    Route::get(
        '/programacion/semestral/filtros',
        [ProgramacionSemestralController::class, 'filtros']
    )
        ->middleware(
            'permission.redirect:ver programacion semestral'
        )
        ->name('programacion.semestral.filtros');

    Route::get(
        '/programacion/asignacion-profesores',
        [AsignacionProfesorController::class, 'index']
    )
        ->middleware(
            'permission.redirect:asignar profesores'
        )
        ->name('programacion.asignacion.profesores');

    Route::get(
        '/programacion/asignacion-profesores/filtros',
        [AsignacionProfesorController::class, 'filtros']
    )
        ->middleware(
            'permission.redirect:asignar profesores'
        )
        ->name('programacion.asignacion.profesores.filtros');

    Route::post(
        '/programacion/asignacion-profesores',
        [AsignacionProfesorController::class, 'store']
    )
        ->middleware(
            'permission.redirect:asignar profesores'
        )
        ->name(
            'programacion.asignacion.profesores.guardar'
        );

    Route::delete(
        '/programacion/asignacion-profesores/{asignacion}',
        [AsignacionProfesorController::class, 'destroy']
    )
        ->middleware(
            'permission.redirect:asignar profesores'
        )
        ->name(
            'programacion.asignacion.profesores.eliminar'
        );

    Route::get(
        '/programacion/asignacion-horarios',
        [AsignacionHorarioController::class, 'index']
    )
        ->middleware(
            'permission.redirect:asignar horarios'
        )
        ->name('programacion.asignacion.horarios');

    Route::get(
        '/programacion/asignacion-horarios/filtros',
        [AsignacionHorarioController::class, 'filtros']
    )
        ->middleware(
            'permission.redirect:asignar horarios'
        )
        ->name('programacion.asignacion.horarios.filtros');

    Route::post(
        '/programacion/asignacion-horarios',
        [AsignacionHorarioController::class, 'store']
    )
        ->middleware(
            'permission.redirect:asignar horarios'
        )
        ->name('programacion.asignacion.horarios.guardar');

    Route::delete(
        '/programacion/asignacion-horarios/{horario}',
        [AsignacionHorarioController::class, 'destroy']
    )
        ->middleware(
            'permission.redirect:asignar horarios'
        )
        ->name('programacion.asignacion.horarios.eliminar');

    Route::get(
        '/programacion/propuesta-horario',
        [PropuestaHorarioController::class, 'index']
    )
        ->middleware(
            'permission.redirect:enviar propuesta horario'
        )
        ->name('programacion.propuesta');

    Route::post(
        '/programacion/propuesta-horario',
        [PropuestaHorarioController::class, 'guardar']
    )
        ->middleware(
            'permission.redirect:enviar propuesta horario'
        )
        ->name('programacion.propuesta.guardar');

    Route::get(
        '/programacion',
        [VisualizacionProgramacionController::class, 'index']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.index');

    Route::get(
        '/programacion/filtros',
        [VisualizacionProgramacionController::class, 'filtros']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.filtros');

    Route::get(
        '/programacion/importar',
        [ImportacionProgramacionController::class, 'index']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.importar');

    Route::post(
        '/programacion/importar/analizar',
        [ImportacionProgramacionController::class, 'analizar']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.importar.analizar');

    Route::post(
        '/programacion/importar/confirmar',
        [ImportacionProgramacionController::class, 'confirmar']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.importar.confirmar');

    Route::post(
        '/programacion/importar/cancelar',
        [ImportacionProgramacionController::class, 'cancelar']
    )
        ->middleware(
            'permission.redirect:ver programacion anterior'
        )
        ->name('programacion.importar.cancelar');

    Route::get(
        '/programacion/intercambio',
        [IntercambioDocenteController::class, 'index']
    )
        ->middleware(
            'permission.redirect:ver intercambio docente'
        )
        ->name('programacion.intercambio');

    Route::get('/programacion/intercambio/carga', [IntercambioDocenteController::class, 'carga'])
        ->middleware('permission.redirect:ver intercambio docente')->name('programacion.intercambio.carga');

    Route::post('/programacion/intercambio/aplicar', [IntercambioDocenteController::class, 'aplicar'])
        ->middleware(['permission.redirect:ver intercambio docente', 'permission.redirect:asignar profesores'])->name('programacion.intercambio.aplicar');

    Route::get('/salas', [SalaController::class, 'index'])
        ->middleware(
            'permission.redirect:gestionar salas y recursos'
        )
        ->name('salas.index');

    Route::get('/salas/horario', [HorarioEspacioController::class, 'index'])
        ->middleware('permission.redirect:gestionar salas y recursos')
        ->name('salas.horario');

    Route::get(
        '/administracion',
        [UsuarioController::class, 'index']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('administracion.index');

    Route::get(
        '/administracion/usuarios/crear',
        [UsuarioController::class, 'create']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('usuarios.create');

    Route::post(
        '/administracion/usuarios',
        [UsuarioController::class, 'store']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('usuarios.store');

    Route::get(
        '/administracion/usuarios/{usuario}/editar',
        [UsuarioController::class, 'edit']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('usuarios.edit');

    Route::put(
        '/administracion/usuarios/{usuario}',
        [UsuarioController::class, 'update']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('usuarios.update');

    Route::delete(
        '/administracion/usuarios/{usuario}',
        [UsuarioController::class, 'destroy']
    )
        ->middleware(
            'permission.redirect:administrar usuarios'
        )
        ->name('usuarios.destroy');

    Route::get('/configuracion', function () {
        return view('configuracion.index');
    })
        ->middleware(
            'permission.redirect:ver configuracion'
        )
        ->name('configuracion.index');
});

Route::get('/prueba-excel', function () {
    $archivo = storage_path('app/prueba.xlsx');

    $datos = Excel::toArray(
        new PruebaImport,
        $archivo
    );

    return response()->json([
        'hojas' => count($datos),
        'filas_primera_hoja' => count($datos[0] ?? []),
        'primeras_filas' => array_slice(
            $datos[0] ?? [],
            0,
            5
        ),
    ]);
})
    ->middleware([
        'auth',
        'permission.redirect:ver panel inicio',
    ]);

Route::fallback(function () {
    return redirect()->route('login');
});
