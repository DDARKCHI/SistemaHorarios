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
use App\Http\Controllers\PrioridadRecursoController;
use App\Http\Controllers\HorarioEspacioController;
use App\Http\Controllers\ValidacionProgramacionController;
use App\Http\Controllers\RequerimientoActividadController;

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
    Route::get('/programacion/validacion/exportar', [ValidacionProgramacionController::class, 'exportar'])
        ->middleware('permission.redirect:gestionar conflictos')->name('programacion.validacion.exportar');

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

    Route::put('/programacion/asignacion-horarios/{horario}/profesor', [AsignacionHorarioController::class, 'asignarProfesor'])
        ->whereNumber('horario')->middleware('permission.redirect:asignar horarios')->name('programacion.asignacion.horarios.profesor');

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
            'permission.redirect:importar programacion anterior'
        )
        ->name('programacion.importar');

    Route::post(
        '/programacion/importar/analizar',
        [ImportacionProgramacionController::class, 'analizar']
    )
        ->middleware(
            'permission.redirect:importar programacion anterior'
        )
        ->name('programacion.importar.analizar');

    Route::post(
        '/programacion/importar/confirmar',
        [ImportacionProgramacionController::class, 'confirmar']
    )
        ->middleware(
            'permission.redirect:importar programacion anterior'
        )
        ->name('programacion.importar.confirmar');

    Route::post(
        '/programacion/importar/cancelar',
        [ImportacionProgramacionController::class, 'cancelar']
    )
        ->middleware(
            'permission.redirect:importar programacion anterior'
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

    Route::prefix('prioridades')->name('prioridades.')->middleware('permission.redirect:gestionar salas y recursos')->group(function () {
        Route::get('/', [PrioridadRecursoController::class, 'index'])->name('index');
        Route::get('/crear', [PrioridadRecursoController::class, 'create'])->name('create');
        Route::post('/', [PrioridadRecursoController::class, 'store'])->name('store');
        Route::get('/{prioridad}/editar', [PrioridadRecursoController::class, 'edit'])->whereNumber('prioridad')->name('edit');
        Route::put('/{prioridad}', [PrioridadRecursoController::class, 'update'])->whereNumber('prioridad')->name('update');
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

    Route::get('/configuracion', [\App\Http\Controllers\ConfiguracionController::class, 'index'])
        ->middleware(
            'permission.redirect:ver configuracion'
        )
        ->name('configuracion.index');
});

require __DIR__.'/disponibilidad.php';

Route::get('/programacion/preparar-generacion', [\App\Http\Controllers\PreparacionGeneracionController::class, 'index'])
    ->middleware(['auth', 'permission.redirect:generar programacion semestral'])->name('programacion.preparacion');

Route::middleware(['auth', 'permission.redirect:generar programacion semestral'])->prefix('programacion/reorganizacion')->name('programacion.reorganizacion.')->group(function () {
    Route::post('/', [\App\Http\Controllers\ReorganizacionAutomaticaController::class, 'generar'])->middleware('throttle:5,1')->name('generar');
    Route::get('/{token}', [\App\Http\Controllers\ReorganizacionAutomaticaController::class, 'show'])->whereUuid('token')->name('show');
    Route::post('/{token}', [\App\Http\Controllers\ReorganizacionAutomaticaController::class, 'guardar'])->whereUuid('token')->name('guardar');
});

Route::middleware(['auth', 'permission.redirect:generar programacion semestral'])->prefix('programacion/version/{version}')->whereNumber('version')->group(function () {
    Route::get('/activar', [\App\Http\Controllers\ActivacionVersionController::class, 'show'])->name('programacion.version.revisar');
    Route::post('/activar', [\App\Http\Controllers\ActivacionVersionController::class, 'store'])->name('programacion.version.activar');
});

Route::middleware(['auth', 'permission.redirect:generar programacion semestral'])->prefix('programacion/version/{version}')->whereNumber('version')->group(function () {
    Route::get('/cierre', [\App\Http\Controllers\CierreProgramacionController::class, 'show'])->name('programacion.version.cierre');
    Route::post('/cierre', [\App\Http\Controllers\CierreProgramacionController::class, 'store'])->name('programacion.version.cerrar');
});
Route::get('/programacion/version/{version}/publicada', [\App\Http\Controllers\CierreProgramacionController::class, 'publicada'])
    ->whereNumber('version')->middleware(['auth', 'permission.redirect:ver programacion semestral'])->name('programacion.version.publicada');

Route::middleware(['auth', 'permission.redirect:generar programacion semestral'])->prefix('programacion/version/{version}')->whereNumber('version')->group(function () {
    Route::get('/copiar', [\App\Http\Controllers\CopiaBorradorController::class, 'show'])->name('programacion.version.copia');
    Route::post('/copiar', [\App\Http\Controllers\CopiaBorradorController::class, 'store'])->name('programacion.version.copiar');
});
Route::get('/programacion/version/{version}/publicada/pdf', [\App\Http\Controllers\CierreProgramacionController::class, 'pdf'])
    ->whereNumber('version')->middleware(['auth', 'permission.redirect:ver programacion semestral', 'throttle:10,1'])->name('programacion.version.pdf');

require __DIR__.'/cambios-horario.php';

require __DIR__.'/estructura-institucional.php';

require __DIR__.'/catalogos-academicos.php';

require __DIR__.'/oferta-academica.php';

require __DIR__.'/secciones-academicas.php';

require __DIR__.'/docentes.php';

require __DIR__.'/completar-programacion.php';
require __DIR__.'/panel-carrera.php';

require __DIR__.'/configuracion.php';

require __DIR__.'/perfiles.php';
require __DIR__.'/identidad.php';

Route::middleware('auth')->group(function () {
    Route::get('/mi-cuenta/cambiar-contrasena', [\App\Http\Controllers\CambiarContrasenaController::class, 'edit'])->name('cuenta.password.edit');
    Route::put('/mi-cuenta/cambiar-contrasena', [\App\Http\Controllers\CambiarContrasenaController::class, 'update'])->middleware('throttle:6,1')->name('cuenta.password.update');
});

require __DIR__.'/manual.php';
require __DIR__.'/accesos-docentes.php';
require __DIR__.'/mi-disponibilidad.php';

Route::fallback(function () {
    return redirect()->route('login');
});
