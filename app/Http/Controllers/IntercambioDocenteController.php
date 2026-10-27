<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\VersionPlanificacion;
use App\Services\EvaluacionReemplazoDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class IntercambioDocenteController extends Controller
{
    public function carga(Request $request)
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
                    ->filter(fn ($participacion) => $participacion->profesor !== null && $participacion->estado)
                    ->groupBy('profesor_id');

                $cargas = HorarioProfesor::query()
                    ->where('estado', true)
                    ->whereIn('profesor_id', $participaciones->keys())
                    ->whereHas('horario', fn ($query) => $query
                        ->where('version_planificacion_id', $versionActual->id)->where('estado', true))
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

        return view('programacion.intercambio-carga', [
            'asignaturas' => $asignaturas,
            'asignatura' => $asignatura,
            'resultados' => $resultados,
            'versionActual' => $versionActual,
        ]);
    }

    public function index(Request $request, EvaluacionReemplazoDocente $evaluador)
    {
        $datos = $request->validate([
            'docente_id' => ['nullable', 'integer', 'min:1', 'required_with:grupo_id,candidato_id'],
            'grupo_id' => ['nullable', 'integer', 'min:1', 'required_with:candidato_id,q'],
            'candidato_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:80'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        [$periodoActual, $versionActual] = $evaluador->contexto();
        $docentes = $cursos = $objetivo = collect();
        $docente = $curso = $comparacion = $candidatos = null;
        $dias = $bloques = collect();
        $huella = '';
        if ($periodoActual && $versionActual) {
            $docentes = $evaluador->docentes($periodoActual, $versionActual)->get(['id', 'rut', 'nombre', 'apellido']);
            if (! empty($datos['docente_id'])) {
                $docente = $docentes->firstWhere('id', (int) $datos['docente_id']);
                if (! $docente) {
                    throw ValidationException::withMessages(['docente_id' => 'El docente no tiene clases activas en esta programación.']);
                }
                $cargaOrigen = $evaluador->carga($docente, $periodoActual, $versionActual);
                $cursos = $cargaOrigen->filter(fn ($h) => $h->grupoSeccion?->estado_activo)->groupBy('grupo_seccion_id')->map(function ($horarios, $grupoId) {
                    $h = $horarios->first();

                    return ['grupo_id' => (int) $grupoId, 'horarios' => $horarios->values(), 'grupo' => $h->grupoSeccion,
                        'actividad' => $h->actividadSeccion, 'seccion' => $h->actividadSeccion->seccion,
                        'asignatura' => $h->actividadSeccion->seccion->asignatura];
                })->values();
                if (! empty($datos['grupo_id'])) {
                    $curso = $cursos->firstWhere('grupo_id', (int) $datos['grupo_id']);
                    if (! $curso) {
                        throw ValidationException::withMessages(['grupo_id' => 'La clase ya no está asignada a este docente en la versión actual.']);
                    }
                    $objetivo = $curso['horarios'];
                    $huella = $evaluador->huella($objetivo);
                    if (filled($datos['q'] ?? null)) {
                        $candidatos = $evaluador->buscar($datos['q'], $docente, $periodoActual)->paginate(8)->withQueryString();
                    }
                    if (! empty($datos['candidato_id'])) {
                        $candidato = Profesor::whereKey($datos['candidato_id'])->where('universidad_id', $periodoActual->universidad_id)->where('estado', true)
                            ->whereKeyNot($docente->id)->first();
                        if (! $candidato) {
                            throw ValidationException::withMessages(['candidato_id' => 'Selecciona otro docente activo de la misma institución.']);
                        }
                        $comparacion = $evaluador->evaluar($candidato, $objetivo, $periodoActual, $versionActual);
                        $todos = $objetivo->concat($comparacion['carga']);
                        $dias = Dia::where(fn ($query) => $query->where('estado', true)->orWhereIn('id', $todos->pluck('dia_id')))->orderBy('numero')->get();
                        $bloques = BloqueHorario::where(fn ($query) => $query->where('estado', true)->orWhereIn('id', $todos->pluck('bloque_horario_id')))->orderBy('hora_inicio')->orderBy('id')->get();
                    }
                }
            }
        }

        return view('programacion.intercambio', compact('periodoActual', 'versionActual', 'docentes', 'docente', 'cursos', 'curso',
            'objetivo', 'candidatos', 'comparacion', 'dias', 'bloques', 'huella', 'datos'));
    }

    public function aplicar(Request $request, EvaluacionReemplazoDocente $evaluador)
    {
        $datos = $request->validate([
            'docente_id' => ['required', 'integer', 'min:1'], 'grupo_id' => ['required', 'integer', 'min:1'],
            'candidato_id' => ['required', 'integer', 'min:1', 'different:docente_id'],
            'periodo_id' => ['required', 'integer', 'min:1'], 'version_id' => ['required', 'integer', 'min:1'],
            'huella' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'confirmar_disponibilidad' => ['nullable', 'boolean'],
        ]);
        $cantidad = $evaluador->aplicar($datos, $request->user()->id, $request->ip());

        return redirect()->route('programacion.intercambio', ['docente_id' => $datos['candidato_id'], 'grupo_id' => $datos['grupo_id']])
            ->with('success', 'Reemplazo aplicado en '.$cantidad.' '.($cantidad === 1 ? 'módulo' : 'módulos').'. Los horarios y los otros docentes de la clase se conservaron.');
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
