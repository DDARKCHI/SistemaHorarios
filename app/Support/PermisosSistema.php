<?php

namespace App\Support;

use App\Models\User;

class PermisosSistema
{
    public const NOMBRES = [
        'ver panel inicio', 'ver programacion anterior', 'importar programacion anterior',
        'ver programacion semestral', 'generar programacion semestral', 'asignar profesores',
        'asignar horarios', 'enviar propuesta horario', 'ver intercambio docente',
        'gestionar conflictos', 'gestionar salas y recursos', 'ver configuracion', 'administrar usuarios',
    ];

    public const DEPENDENCIAS = [
        'importar programacion anterior' => 'ver programacion anterior',
        'generar programacion semestral' => 'ver programacion semestral',
        'asignar profesores' => 'ver programacion semestral',
        'asignar horarios' => 'ver programacion semestral',
        'gestionar conflictos' => 'ver programacion semestral',
    ];

    public static function grupos(): array
    {
        return [
            'Consulta' => [
                'ver panel inicio' => ['Inicio y resumen', 'Consultar indicadores generales del sistema.'],
                'ver programacion semestral' => ['Programación semestral', 'Consultar horarios y descargar publicaciones y sus reportes.'],
                'ver programacion anterior' => ['Programación anterior', 'Consultar versiones anteriores y sus filtros.'],
                'ver intercambio docente' => ['Evaluación de reemplazos', 'Consultar candidatos, carga docente e historial. Aplicar requiere también asignación de docentes.'],
            ],
            'Programación' => [
                'importar programacion anterior' => ['Importación de Excel', 'Analizar archivos y confirmar la creación de versiones anteriores y sus catálogos.'],
                'generar programacion semestral' => ['Versiones, generación y publicación', 'Crear borradores, generar horarios, activar, aprobar y publicar versiones.'],
                'asignar profesores' => ['Asignación y disponibilidad docente', 'Asignar docentes, solicitar y aprobar disponibilidad y aplicar reemplazos.'],
                'asignar horarios' => ['Asignación de horarios y cambios', 'Modificar clases y recursos, gestionar solicitudes de cambio y requerimientos junto con revisión de conflictos.'],
                'enviar propuesta horario' => ['Disponibilidad personal', 'Registrar la disponibilidad propia y consultar sus respuestas.'],
                'gestionar conflictos' => ['Conflictos y requerimientos', 'Revisar observaciones, exportarlas y consultar requerimientos. Editarlos requiere también asignación de horarios.'],
            ],
            'Gestión institucional' => [
                'gestionar salas y recursos' => ['Salas, laboratorios y equipamiento', 'Administrar espacios, inventario, distribución y prioridades de recursos.'],
                'ver configuracion' => ['Configuración y calendario', 'Consultar datos institucionales, días y módulos. Modificar requiere también el permiso de cuentas, perfiles y catálogos.'],
                'administrar usuarios' => ['Cuentas, perfiles y catálogos', 'Administrar accesos y permisos, docentes, carreras y toda la oferta académica. Otorga funciones administrativas amplias.'],
            ],
        ];
    }

    public static function entrada(User $usuario): ?string
    {
        if ($usuario->profesor_id && $usuario->profesor?->estado
            && ! $usuario->can('ver panel inicio') && ! $usuario->can('asignar profesores')
            && ! $usuario->can('administrar usuarios')) {
            return 'mi.disponibilidad.index';
        }
        foreach ([
            'ver panel inicio' => 'inicio', 'ver programacion semestral' => 'programacion.semestral',
            'ver programacion anterior' => 'programacion.index', 'importar programacion anterior' => 'programacion.importar',
            'administrar usuarios' => 'administracion.index', 'gestionar salas y recursos' => 'salas.index',
            'asignar profesores' => 'programacion.asignacion.profesores', 'asignar horarios' => 'programacion.asignacion.horarios',
            'generar programacion semestral' => 'programacion.preparacion', 'gestionar conflictos' => 'programacion.validacion',
            'enviar propuesta horario' => 'programacion.propuesta', 'ver intercambio docente' => 'programacion.intercambio',
            'ver configuracion' => 'configuracion.index',
        ] as $permiso => $ruta) {
            if ($usuario->can($permiso)) return $ruta;
        }
        return null;
    }
}
