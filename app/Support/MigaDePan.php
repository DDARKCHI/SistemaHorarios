<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class MigaDePan
{
    private const PAGINAS = [
        'inicio' => ['Inicio'],
        'ayuda.manual' => ['Manual de usuario'],
        'cuenta.password.edit' => ['Cambiar contraseña'],
        'mi.disponibilidad.index' => ['Mi disponibilidad'],
        'mi.disponibilidad.show' => ['Informar disponibilidad', 'mi.disponibilidad.index'],
        'programacion.completar.index' => ['Completar programación', 'programacion.semestral'],
        'programacion.completar.show' => ['Propuesta de carga pendiente', 'programacion.completar.index'],
        'administracion.docentes.index' => ['Docentes', 'administracion.index'],
        'administracion.docentes.create' => ['Registrar docente', 'administracion.docentes.index'],
        'administracion.docentes.edit' => ['Editar docente', 'administracion.docentes.index'],
        'administracion.secciones.index' => ['Oferta por período', 'administracion.index'],
        'administracion.secciones.create' => ['Crear sección', 'administracion.secciones.index'],
        'administracion.secciones.show' => ['Detalle de la oferta', 'administracion.secciones.index'],
        'administracion.secciones.edit' => ['Editar sección', 'administracion.secciones.show'],
        'administracion.secciones.actividad.create' => ['Agregar actividad', 'administracion.secciones.show'],
        'administracion.secciones.actividad.edit' => ['Editar actividad', 'administracion.secciones.show'],
        'administracion.secciones.grupo.create' => ['Agregar grupo', 'administracion.secciones.show'],
        'administracion.secciones.grupo.edit' => ['Editar grupo', 'administracion.secciones.show'],
        'administracion.oferta.index' => ['Oferta académica', 'administracion.index'],
        'administracion.oferta.create' => ['Crear registro', 'administracion.oferta.index'],
        'administracion.oferta.edit' => ['Editar registro', 'administracion.oferta.index'],
        'administracion.academicos.index' => ['Catálogos académicos', 'administracion.index'],
        'administracion.academicos.create' => ['Crear registro', 'administracion.academicos.index'],
        'administracion.academicos.edit' => ['Editar registro', 'administracion.academicos.index'],
        'administracion.estructura.index' => ['Estructura institucional', 'administracion.index'],
        'administracion.estructura.create' => ['Crear registro', 'administracion.estructura.index'],
        'administracion.estructura.edit' => ['Editar registro', 'administracion.estructura.index'],
        'programacion.cambios.index' => ['Solicitudes de cambio', 'programacion.semestral'],
        'programacion.cambios.create' => ['Solicitar cambio', 'programacion.cambios.index'],
        'programacion.cambios.show' => ['Detalle de solicitud', 'programacion.cambios.index'],
        'programacion.version.copia' => ['Crear borrador para ajustes', 'programacion.semestral'],
        'programacion.version.cierre' => ['Aprobación y publicación', 'programacion.semestral'],
        'programacion.version.publicada' => ['Horario publicado', 'programacion.semestral'],
        'programacion.version.revisar' => ['Activar versión', 'programacion.semestral'],
        'programacion.reorganizacion.show' => ['Propuesta automática', 'programacion.preparacion'],
        'programacion.preparacion' => ['Preparar generación', 'programacion.semestral'],
        'disponibilidad.index' => ['Disponibilidad docente'],
        'disponibilidad.show' => ['Revisar disponibilidad', 'disponibilidad.index'],
        'programacion.semestral' => ['Programación semestral'],
        'programacion.validacion' => ['Revisión de conflictos', 'programacion.semestral'],
        'programacion.requerimientos.index' => ['Requerimientos de actividades', 'programacion.validacion'],
        'programacion.requerimientos.create' => ['Agregar requerimiento', 'programacion.requerimientos.index'],
        'programacion.requerimientos.edit' => ['Editar requerimiento', 'programacion.requerimientos.index'],
        'programacion.asignacion.profesores' => ['Asignación de docentes', 'programacion.semestral'],
        'programacion.asignacion.horarios' => ['Asignación de horarios', 'programacion.semestral'],
        'programacion.propuesta' => ['Propuesta de horario', 'programacion.semestral'],
        'programacion.intercambio' => ['Intercambio docente'],
        'programacion.intercambio.carga' => ['Carga docente por ramo', 'programacion.intercambio'],
        'programacion.intercambio.historial' => ['Historial de reemplazos', 'programacion.intercambio'],
        'programacion.intercambio.historial.detalle' => ['Detalle del reemplazo', 'programacion.intercambio.historial'],
        'salas.index' => ['Salas y recursos'],
        'salas.create' => ['Crear sala', 'salas.index'],
        'salas.edit' => ['Editar sala', 'salas.index'],
        'salas.horario' => ['Horario de espacios', 'salas.index'],
        'laboratorios.index' => ['Laboratorios', 'salas.index'],
        'laboratorios.create' => ['Crear laboratorio', 'laboratorios.index'],
        'laboratorios.edit' => ['Editar laboratorio', 'laboratorios.index'],
        'recursos.index' => ['Equipamiento y recursos', 'salas.index'],
        'recursos.create' => ['Registrar equipamiento', 'recursos.index'],
        'recursos.edit' => ['Editar equipamiento', 'recursos.index'],
        'recursos.ubicaciones.index' => ['Ubicaciones del equipamiento', 'recursos.index'],
        'prioridades.index' => ['Prioridades de recursos', 'salas.index'],
        'prioridades.create' => ['Crear prioridad', 'prioridades.index'],
        'prioridades.edit' => ['Editar prioridad', 'prioridades.index'],
        'programacion.index' => ['Programación anterior'],
        'programacion.importar' => ['Cargar programación anterior', 'programacion.index'],
        'programacion.importar.analizar' => ['Cargar programación anterior', 'programacion.index'],
        'administracion.index' => ['Administración'],
        'administracion.perfiles.index' => ['Perfiles y permisos', 'administracion.index'],
        'administracion.perfiles.create' => ['Crear perfil', 'administracion.perfiles.index'],
        'administracion.perfiles.edit' => ['Revisar perfil', 'administracion.perfiles.index'],
        'usuarios.create' => ['Crear usuario', 'administracion.index'],
        'usuarios.edit' => ['Editar usuario', 'administracion.index'],
        'configuracion.index' => ['Configuración'],
        'configuracion.identidad' => ['Identidad visual', 'configuracion.index'],
        'configuracion.calendario' => ['Calendario académico', 'configuracion.index'],
        'configuracion.calendario.create' => ['Agregar al calendario', 'configuracion.calendario'],
        'configuracion.calendario.edit' => ['Editar calendario', 'configuracion.calendario'],
    ];

    private const PERMISOS = [
        'administracion.perfiles.index' => 'administrar usuarios',
        'inicio' => 'ver panel inicio',
        'programacion.completar.index' => 'generar programacion semestral',
        'administracion.docentes.index' => 'administrar usuarios',
        'administracion.secciones.index' => 'administrar usuarios',
        'administracion.secciones.show' => 'administrar usuarios',
        'configuracion.index' => 'ver configuracion',
        'configuracion.calendario' => 'ver configuracion',
        'administracion.oferta.index' => 'administrar usuarios',
        'administracion.academicos.index' => 'administrar usuarios',
        'administracion.estructura.index' => 'administrar usuarios',
        'programacion.cambios.index' => 'asignar horarios',
        'programacion.preparacion' => 'generar programacion semestral',
        'disponibilidad.index' => 'asignar profesores',
        'mi.disponibilidad.index' => 'enviar propuesta horario',
        'programacion.semestral' => 'ver programacion semestral',
        'programacion.validacion' => 'gestionar conflictos',
        'programacion.requerimientos.index' => 'gestionar conflictos',
        'programacion.index' => 'ver programacion anterior',
        'administracion.index' => 'administrar usuarios',
        'salas.index' => 'gestionar salas y recursos',
        'laboratorios.index' => 'gestionar salas y recursos',
        'recursos.index' => 'gestionar salas y recursos',
        'prioridades.index' => 'gestionar salas y recursos',
        'programacion.intercambio' => 'ver intercambio docente',
        'programacion.intercambio.historial' => 'ver intercambio docente',
    ];

    /** @return array<int, array{label: string, url: ?string, current: bool}> */
    public static function para(Request $request, ?User $usuario): array
    {
        $rutaActual = $request->route()?->getName();
        $pagina = self::PAGINAS[$rutaActual] ?? ['Página actual'];
        if (str_starts_with($rutaActual ?? '', 'administracion.academicos.') || str_starts_with($rutaActual ?? '', 'administracion.oferta.')) {
            [$plural, $singular] = self::nombreCatalogo($request->route('tipo'));
            $pagina[0] = str_ends_with($rutaActual, '.index') ? $plural : (str_ends_with($rutaActual, '.create') ? 'Crear ' : 'Editar ').$singular;
        }
        if (str_starts_with($rutaActual ?? '', 'administracion.estructura.')) {
            $nombre = $request->route('tipo') === 'carreras' ? 'Carrera' : 'Sede';
            $pagina[0] = str_ends_with($rutaActual, '.index') ? ($nombre === 'Sede' ? 'Sedes' : 'Carreras') : (str_ends_with($rutaActual, '.create') ? 'Crear ' : 'Editar ').mb_strtolower($nombre);
        }
        $migas = [];

        if ($rutaActual !== 'inicio' && $usuario?->can(self::PERMISOS['inicio'])) {
            $migas[] = ['label' => 'Inicio', 'url' => route('inicio'), 'current' => false];
        }

        if (isset($pagina[1])) {
            $rutaPadre = $pagina[1];
            $puedeConsultar = $rutaPadre === 'mi.disponibilidad.index'
                ? (bool) $usuario?->profesor?->estado
                : $usuario?->can(self::PERMISOS[$rutaPadre]);
            $parametros = in_array($rutaPadre, ['programacion.semestral', 'programacion.index', 'programacion.validacion', 'programacion.requerimientos.index', 'programacion.completar.index'], true)
                ? self::contextoProgramacion($request)
                : [];
            if ($rutaPadre === 'programacion.requerimientos.index' && $request->route('actividad') instanceof \App\Models\ActividadSeccion) {
                $parametros['periodo_id'] = $request->route('actividad')->seccion->periodo_academico_id;
            }
            if (in_array($rutaActual, ['programacion.version.revisar', 'programacion.version.cierre', 'programacion.version.publicada', 'programacion.version.copia'], true) && $request->route('version') instanceof \App\Models\VersionPlanificacion) {
                $parametros = ['periodo_id' => $request->route('version')->periodo_academico_id, 'version_id' => $request->route('version')->id];
            }
            if ($rutaPadre === 'administracion.estructura.index') $parametros = ['tipo' => $request->route('tipo')];
            if ($rutaPadre === 'administracion.academicos.index') $parametros = ['tipo' => $request->route('tipo')];
            if ($rutaPadre === 'administracion.oferta.index') $parametros = ['tipo' => $request->route('tipo')];
            if ($rutaPadre === 'administracion.secciones.show') $parametros = ['seccion' => $request->route('seccion')];
            if ($rutaPadre === 'administracion.secciones.index') $parametros = $request->route('seccion') instanceof \App\Models\Seccion
                ? ['periodo_id' => $request->route('seccion')->periodo_academico_id] : self::contextoProgramacion($request);
            if ($rutaPadre === 'laboratorios.index' && in_array($request->route('tipo'), ['fijo', 'movil'], true)) {
                $parametros['tipo'] = $request->route('tipo');
            }

            $migas[] = [
                'label' => in_array($rutaPadre, ['administracion.academicos.index', 'administracion.oferta.index'], true) ? self::nombreCatalogo($request->route('tipo'))[0] : self::PAGINAS[$rutaPadre][0],
                'url' => $puedeConsultar ? route($rutaPadre, $parametros) : null,
                'current' => false,
            ];
        }

        $migas[] = ['label' => $pagina[0], 'url' => null, 'current' => true];

        return $migas;
    }

    private static function nombreCatalogo(?string $tipo): array
    {
        return match ($tipo) {
            'asignaturas' => ['Asignaturas y ramos', 'asignatura'],
            'periodos' => ['Períodos académicos', 'período académico'],
            'facultades' => ['Facultades e institutos', 'unidad académica'],
            'planes' => ['Planes de estudio', 'plan de estudio'],
            'niveles' => ['Semestres de carrera', 'semestre de carrera'],
            default => ['Catálogos académicos', 'registro'],
        };
    }

    private static function contextoProgramacion(Request $request): array
    {
        $parametros = [];

        // Conservar el contexto sin propagar búsquedas, datos de formularios ni consultas arbitrarias.
        foreach (['periodo_id', 'version_id'] as $clave) {
            $valor = $request->query($clave);
            $id = is_scalar($valor)
                ? filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;

            if ($id !== false) {
                $parametros[$clave] = $id;
            }
        }

        return $parametros;
    }
}
