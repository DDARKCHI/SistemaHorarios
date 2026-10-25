<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\VersionPlanificacion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InicioController extends Controller
{
    public function index(Request $request): View
    {
        $periodoActivo = PeriodoAcademico::where('estado', true)
            ->orderByDesc('anio')
            ->orderByDesc('numero_semestre')
            ->orderByDesc('id')
            ->first();

        $versionSemestral = $periodoActivo
            ? VersionPlanificacion::where('periodo_academico_id', $periodoActivo->id)
                ->where('tipo', 'semestral')
                ->where('es_actual', true)
                ->orderByDesc('id')
                ->first()
            : null;

        $totales = [
            'carreras' => Carrera::where('estado', true)->count(),
            'profesores' => Profesor::where('estado', true)->count(),
            'salas' => Sala::where('estado', true)->count(),
            'secciones' => $periodoActivo
                ? Seccion::where('periodo_academico_id', $periodoActivo->id)->where('estado_activo', true)->count()
                : 0,
        ];

        $resumen = ['modulos' => 0, 'grupos' => 0, 'secciones' => 0];
        if ($versionSemestral) {
            $conteos = Horario::join('actividades_seccion as actividad', 'actividad.id', '=', 'horarios.actividad_seccion_id')
                ->where('horarios.version_planificacion_id', $versionSemestral->id)
                ->where('horarios.estado', true)
                ->selectRaw('COUNT(*) AS modulos, COUNT(DISTINCT horarios.grupo_seccion_id) AS grupos, COUNT(DISTINCT actividad.seccion_id) AS secciones')
                ->first();

            $resumen = [
                'modulos' => (int) $conteos->modulos,
                'grupos' => (int) $conteos->grupos,
                'secciones' => (int) $conteos->secciones,
            ];
        }

        $filtroPeriodo = $periodoActivo ? ['periodo_id' => $periodoActivo->id] : [];
        $filtrosProgramacion = $filtroPeriodo + ($versionSemestral ? ['version_id' => $versionSemestral->id] : []);
        $urlProgramacion = route('programacion.semestral', $filtrosProgramacion);

        $accesos = collect([
            ['asignar profesores', 'Asignar profesores', 'programacion.asignacion.profesores', $filtroPeriodo],
            ['asignar horarios', 'Asignar horarios', 'programacion.asignacion.horarios', $filtroPeriodo],
            ['enviar propuesta horario', 'Mi disponibilidad', 'programacion.propuesta', []],
            ['ver intercambio docente', 'Consultar intercambio docente', 'programacion.intercambio', []],
            ['ver programacion anterior', 'Consultar programación anterior', 'programacion.index', $filtroPeriodo],
            ['administrar usuarios', 'Administrar usuarios', 'administracion.index', []],
        ])
            ->filter(fn (array $acceso) => $request->user()->can($acceso[0]))
            ->map(fn (array $acceso) => ['titulo' => $acceso[1], 'url' => route($acceso[2], $acceso[3])])
            ->values()
            ->all();

        return view('inicio', compact(
            'periodoActivo', 'versionSemestral', 'totales', 'resumen', 'accesos', 'urlProgramacion'
        ));
    }
}
