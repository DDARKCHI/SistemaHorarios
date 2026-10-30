<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\ImportacionProgramacionController;
use App\Http\Controllers\VisualizacionProgramacionController;
use App\Http\Controllers\ProgramacionSemestralController;
use App\Http\Controllers\IntercambioDocenteController;
use App\Http\Controllers\HistorialReemplazoController;
use App\Http\Controllers\PropuestaHorarioController;
use App\Http\Controllers\AsignacionProfesorController;
use App\Http\Controllers\AsignacionHorarioController;
use App\Http\Controllers\RecuperarContrasenaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\LaboratorioController;
use App\Http\Controllers\EquipamientoController;
use App\Http\Controllers\EquipamientoEspacioController;
use App\Http\Controllers\HorarioEspacioController;
use App\Http\Controllers\ValidacionProgramacionController;
use App\Http\Controllers\RequerimientoActividadController;
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

    Route::get('/programacion/validacion', [ValidacionProgramacionController::class, 'index'])
        ->middleware('permission.redirect:gestionar conflictos')->name('programacion.validacion');

    Route::get('/programacion/requerimientos', [RequerimientoActividadController::class, 'index'])
        ->middleware('permission.redirect:gestionar conflictos')->name('programacion.requerimientos.index');
    Route::scopeBindings()->prefix('programacion/actividades/{actividad}/requerimientos')->whereNumber('actividad')
        ->middleware(['permission.redirect:gestionar conflictos', 'permission.redirect:asignar horarios'])->name('programacion.requerimientos.')->group(function () {
            Route::get('/crear', [RequerimientoActividadController::class, 'create'])->name('create');
            Route::post('/', [RequerimientoActividadController::class, 'store'])->name('store');
            Route::get('/{requerimiento}/editar', [RequerimientoActividadController::class, 'edit'])->whereNumber('requerimiento')->name('edit');
            Route::put('/{requerimiento}', [RequerimientoActividadController::class, 'update'])->whereNumber('requerimiento')->name('update');
        });

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

    Route::get('/programacion/intercambio/historial', [HistorialReemplazoController::class, 'index'])
        ->middleware('permission.redirect:ver intercambio docente')->name('programacion.intercambio.historial');
    Route::get('/programacion/intercambio/historial/{registro}', [HistorialReemplazoController::class, 'show'])
        ->whereNumber('registro')->middleware('permission.redirect:ver intercambio docente')->name('programacion.intercambio.historial.detalle');

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

    Route::get('/salas/crear', [SalaController::class, 'create'])
        ->middleware('permission.redirect:gestionar salas y recursos')->name('salas.create');
    Route::post('/salas', [SalaController::class, 'store'])
        ->middleware('permission.redirect:gestionar salas y recursos')->name('salas.store');
    Route::get('/salas/{sala}/editar', [SalaController::class, 'edit'])
        ->whereNumber('sala')->middleware('permission.redirect:gestionar salas y recursos')->name('salas.edit');
    Route::put('/salas/{sala}', [SalaController::class, 'update'])
        ->whereNumber('sala')->middleware('permission.redirect:gestionar salas y recursos')->name('salas.update');

    Route::prefix('laboratorios')->name('laboratorios.')->middleware('permission.redirect:gestionar salas y recursos')->group(function () {
        Route::get('/', [LaboratorioController::class, 'index'])->name('index');
        Route::get('/{tipo}/crear', [LaboratorioController::class, 'create'])->whereIn('tipo', ['fijo', 'movil'])->name('create');
        Route::post('/{tipo}', [LaboratorioController::class, 'store'])->whereIn('tipo', ['fijo', 'movil'])->name('store');
        Route::get('/{tipo}/{id}/editar', [LaboratorioController::class, 'edit'])->whereIn('tipo', ['fijo', 'movil'])->whereNumber('id')->name('edit');
        Route::put('/{tipo}/{id}', [LaboratorioController::class, 'update'])->whereIn('tipo', ['fijo', 'movil'])->whereNumber('id')->name('update');
    });

    Route::prefix('recursos')->name('recursos.')->middleware('permission.redirect:gestionar salas y recursos')->group(function () {
        Route::get('/', [EquipamientoController::class, 'index'])->name('index');
        Route::get('/crear', [EquipamientoController::class, 'create'])->name('create');
        Route::post('/', [EquipamientoController::class, 'store'])->name('store');
        Route::get('/{equipamiento}/editar', [EquipamientoController::class, 'edit'])->whereNumber('equipamiento')->name('edit');
        Route::get('/{equipamiento}/ubicaciones', [EquipamientoEspacioController::class, 'index'])->whereNumber('equipamiento')->name('ubicaciones.index');
        Route::post('/{equipamiento}/ubicaciones', [EquipamientoEspacioController::class, 'store'])->whereNumber('equipamiento')->name('ubicaciones.store');
        Route::put('/{equipamiento}/ubicaciones/{ubicacion}', [EquipamientoEspacioController::class, 'update'])->whereNumber(['equipamiento', 'ubicacion'])->name('ubicaciones.update');
        Route::delete('/{equipamiento}/ubicaciones/{ubicacion}', [EquipamientoEspacioController::class, 'destroy'])->whereNumber(['equipamiento', 'ubicacion'])->name('ubicaciones.destroy');
        Route::put('/{equipamiento}', [EquipamientoController::class, 'update'])->whereNumber('equipamiento')->name('update');
    });

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
