<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\VersionPlanificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class IntercambioDocenteController extends Controller
{
    public function index(Request $request)
    {
        $periodoActual = PeriodoAcademico::where('estado', true)
            ->orderByDesc('anio')
            ->orderByDesc('numero_semestre')
            ->orderByDesc('id')
            ->first();

        $versionActual = $periodoActual
            ? VersionPlanificacion::where('periodo_academico_id', $periodoActual->id)
                ->where('tipo', 'semestral')
                ->where('es_actual', true)
                ->latest('id')
                ->first()
            : null;

        $asignaturas = Asignatura::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $asignatura = null;
        $resultados = collect();

        if ($request->filled('asignatura_id')) {
            $asignatura = Asignatura::with([
                'secciones' => fn ($query) => $query
                    ->where('periodo_academico_id', $periodoActual?->id)
                    ->with('profesores.profesor'),
            ])->find($request->asignatura_id);

            if ($asignatura && $versionActual) {
                // Agrupar antes de consultar la carga conserva todas las secciones del docente.
                $participaciones = $asignatura->secciones
                    ->flatMap(fn ($seccion) => $seccion->profesores)
                    ->filter(fn ($participacion) => $participacion->profesor !== null)
                    ->groupBy('profesor_id');

                $cargas = HorarioProfesor::query()
                    ->whereIn('profesor_id', $participaciones->keys())
                    ->whereHas('horario', fn ($query) => $query
                        ->where('version_planificacion_id', $versionActual->id))
                    ->with([
                        'horario.dia',
                        'horario.bloqueHorario',
                        'horario.actividadSeccion.seccion.asignatura',
                        'horario.grupoSeccion',
                        'horario.asignacionesSalas.sala.sede',
                    ])
                    ->get()
                    ->groupBy('profesor_id');

                $resultados = $participaciones->map(function ($vinculos, $profesorId) use ($cargas) {
                    $participacion = $vinculos->first();
                    $secciones = $vinculos->pluck('seccion_id');
                    $horariosCarga = $cargas->get($profesorId, collect())
                        ->pluck('horario')
                        ->filter()
                        ->unique('id')
                        ->values();

                    $horariosAsignatura = $horariosCarga
                        ->filter(fn ($horario) => $horario->grupoSeccion !== null
                            && $secciones->contains($horario->actividadSeccion?->seccion_id))
                        ->map(fn ($horario) => [
                            'horario' => $horario,
                            'grupo' => $horario->grupoSeccion,
                        ])
                        ->values();

                    $asignaturasCarga = $horariosCarga
                        ->map(fn ($horario) => $horario->actividadSeccion?->seccion?->asignatura)
                        ->filter()
                        ->unique('id')
                        ->values();

                    $horariosOcupados = $horariosCarga->map(fn ($horario) => [
                        'horario_id' => $horario->id,
                        'dia_id' => $horario->dia_id,
                        'dia' => $horario->dia?->nombre,
                        'bloque_id' => $horario->bloque_horario_id,
                        'bloque' => $horario->bloqueHorario?->numero,
                        'hora_inicio' => $horario->bloqueHorario?->hora_inicio,
                        'hora_fin' => $horario->bloqueHorario?->hora_fin,
                        'asignatura' => $horario->actividadSeccion?->seccion?->asignatura,
                    ])->values();

                    $cargaPorAsignatura = $horariosCarga
                        ->groupBy(fn ($horario) => $horario->actividadSeccion?->seccion?->asignatura?->id)
                        ->map(fn ($horarios) => [
                            'asignatura' => $horarios->first()->actividadSeccion?->seccion?->asignatura,
                            'cantidad_horarios' => $horarios->count(),
                            'horarios' => $horarios->values(),
                        ])
                        ->filter(fn ($carga) => $carga['asignatura'] !== null)
                        ->values();

                    return [
                        'profesor' => $participacion->profesor,
                        'sedes' => $this->sedesDeHorarios($horariosAsignatura->pluck('horario')),
                        'sedes_carga' => $this->sedesDeHorarios($horariosCarga),
                        'horarios' => $horariosAsignatura,
                        'horarios_carga' => $horariosCarga,
                        'horarios_ocupados' => $horariosOcupados,
                        'carga_por_asignatura' => $cargaPorAsignatura,
                        'cantidad_horarios' => $horariosCarga->count(),
                        'cantidad_asignaturas' => $asignaturasCarga->count(),
                        'asignaturas_carga' => $asignaturasCarga,
                        // Mantener los metadatos del primer vínculo, como en la consulta anterior.
                        'tipo_participacion' => $participacion->tipo_participacion,
                        'es_principal' => $participacion->es_principal,
                    ];
                })->values();
            }
        }

        return view('programacion.intercambio', [
            'asignaturas' => $asignaturas,
            'asignatura' => $asignatura,
            'resultados' => $resultados,
            'versionActual' => $versionActual,
        ]);
    }

    private function sedesDeHorarios(Collection $horarios): Collection
    {
        return $horarios
            ->flatMap(fn ($horario) => $horario->asignacionesSalas
                ->map(fn ($asignacion) => $asignacion->sala?->sede))
            ->filter()
            ->unique('id')
            ->values();
    }
}
