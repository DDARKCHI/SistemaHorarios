<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class MigaDePan
{
    private const PAGINAS = [
        'inicio' => ['Inicio'],
        'programacion.semestral' => ['Programación semestral'],
        'programacion.validacion' => ['Revisión de conflictos', 'programacion.semestral'],
        'programacion.asignacion.profesores' => ['Asignación de profesores', 'programacion.semestral'],
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
        'programacion.index' => ['Programación anterior'],
        'programacion.importar' => ['Cargar programación anterior', 'programacion.index'],
        'programacion.importar.analizar' => ['Cargar programación anterior', 'programacion.index'],
        'administracion.index' => ['Administración'],
        'usuarios.create' => ['Crear usuario', 'administracion.index'],
        'usuarios.edit' => ['Editar usuario', 'administracion.index'],
        'configuracion.index' => ['Configuración'],
    ];

    private const PERMISOS = [
        'inicio' => 'ver panel inicio',
        'programacion.semestral' => 'ver programacion semestral',
        'programacion.index' => 'ver programacion anterior',
        'administracion.index' => 'administrar usuarios',
        'salas.index' => 'gestionar salas y recursos',
        'laboratorios.index' => 'gestionar salas y recursos',
        'recursos.index' => 'gestionar salas y recursos',
        'programacion.intercambio' => 'ver intercambio docente',
        'programacion.intercambio.historial' => 'ver intercambio docente',
    ];

    /** @return array<int, array{label: string, url: ?string, current: bool}> */
    public static function para(Request $request, ?User $usuario): array
    {
        $rutaActual = $request->route()?->getName();
        $pagina = self::PAGINAS[$rutaActual] ?? ['Página actual'];
        $migas = [];

        if ($rutaActual !== 'inicio' && $usuario?->can(self::PERMISOS['inicio'])) {
            $migas[] = ['label' => 'Inicio', 'url' => route('inicio'), 'current' => false];
        }

        if (isset($pagina[1])) {
            $rutaPadre = $pagina[1];
            $puedeConsultar = $usuario?->can(self::PERMISOS[$rutaPadre]);
            $parametros = in_array($rutaPadre, ['programacion.semestral', 'programacion.index'], true)
                ? self::contextoProgramacion($request)
                : [];
            if ($rutaPadre === 'laboratorios.index' && in_array($request->route('tipo'), ['fijo', 'movil'], true)) {
                $parametros['tipo'] = $request->route('tipo');
            }

            $migas[] = [
                'label' => self::PAGINAS[$rutaPadre][0],
                'url' => $puedeConsultar ? route($rutaPadre, $parametros) : null,
                'current' => false,
            ];
        }

        $migas[] = ['label' => $pagina[0], 'url' => null, 'current' => true];

        return $migas;
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
