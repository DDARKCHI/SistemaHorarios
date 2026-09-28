<?php

namespace App\Http\Controllers;

use App\Models\BloqueHorario;
use App\Models\Carrera;
use App\Models\Dia;
use App\Models\DisponibilidadProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Seccion;
use App\Models\VersionPlanificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignacionHorarioController extends Controller
{
    public function index(Request $request)
    {
        $periodos = PeriodoAcademico::where('estado', true)
            ->orderByDesc('anio')
            ->orderByDesc('numero_semestre')
            ->get();

        $periodoId = $request->integer('periodo_id')
            ?: $periodos->first()?->id;

        $carreraId = $request->integer('carrera_id');
        $semestre = $request->input('semestre');
        $seccionId = $request->integer('seccion_id');

        $carreras = $this->obtenerCarreras($periodoId);

        $semestres = $this->obtenerSemestres(
            $periodoId,
            $carreraId
        );

        $seccionesFiltro = $this->obtenerSecciones(
            $periodoId,
            $carreraId,
            $semestre
        );

        $version = $this->obtenerVersionSemestral($periodoId);

        $dias = Dia::where('estado', true)
            ->orderBy('numero')
            ->get();

        $bloques = BloqueHorario::where('estado', true)
            ->orderBy('numero')
            ->get();

        $seccion = null;

        if (
            $seccionId
            && $version
            && $seccionesFiltro->contains('id', $seccionId)
        ) {
            $seccion = Seccion::with([
                'asignatura',
                'carreras',

                'actividades' => function ($query) {
                    $query->where('estado', true)
                        ->orderBy('tipo');
                },

                'actividades.grupos' => function ($query) {
                    $query->where('estado_activo', true)
                        ->orderBy('numero');
                },

                'actividades.grupos.profesores' => function ($query) {
                    $query->where('estado', true)
                        ->orderByDesc('es_principal');
                },

                'actividades.grupos.profesores.profesor',

                'actividades.grupos.profesores.profesor.disponibilidades'
                    => function ($query) use ($periodoId) {
                        $query->where(
                            'periodo_academico_id',
                            $periodoId
                        )
                            ->where('estado', true);
                    },

                'actividades.grupos.horarios'
                    => function ($query) use ($version) {
                        $query->where(
                            'version_planificacion_id',
                            $version->id
                        )
                            ->where('estado', true)
                            ->orderBy('dia_id')
                            ->orderBy('bloque_horario_id');
                    },

                'actividades.grupos.horarios.dia',
                'actividades.grupos.horarios.bloqueHorario',
                'actividades.grupos.horarios.profesores.profesor',
            ])
                ->where('id', $seccionId)
                ->where(
                    'periodo_academico_id',
                    $periodoId
                )
                ->where('estado_activo', true)
                ->first();
        }

        return view(
            'programacion.asignacion-horarios',
            compact(
                'periodos',
                'periodoId',
                'carreras',
                'carreraId',
                'semestres',
                'semestre',
                'seccionesFiltro',
                'seccionId',
                'version',
                'dias',
                'bloques',
                'seccion'
            )
        );
    }

    public function filtros(Request $request)
    {
        $periodoId = $request->integer('periodo_id');
        $carreraId = $request->integer('carrera_id');
        $semestre = $request->input('semestre');

        if (!$periodoId) {
            return response()->json([
                'carreras' => [],
                'semestres' => [],
                'secciones' => [],
            ]);
        }

        $carreras = $this->obtenerCarreras($periodoId)
            ->map(function ($carrera) {
                return [
                    'id' => $carrera->id,
                    'nombre' => $carrera->nombre,
                ];
            })
            ->values();

        $semestres = $this->obtenerSemestres(
            $periodoId,
            $carreraId
        );

        $secciones = $this->obtenerSecciones(
            $periodoId,
            $carreraId,
            $semestre
        )
            ->map(function ($seccion) {
                return [
                    'id' => $seccion->id,
                    'texto' => $seccion->codigo
                        . ' — '
                        . ($seccion->asignatura?->codigo ?? '')
                        . ' '
                        . ($seccion->asignatura?->nombre
                            ?? $seccion->nombre
                            ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'carreras' => $carreras,
            'semestres' => $semestres,
            'secciones' => $secciones,
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'grupo_seccion_id' => [
                'required',
                'exists:grupos_seccion,id',
            ],

            'dia_id' => [
                'required',
                'exists:dias,id',
            ],

            'bloques_horarios' => [
                'required',
                'array',
                'min:1',
            ],

            'bloques_horarios.*' => [
                'required',
                'integer',
                'distinct',
                'exists:bloques_horarios,id',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'grupo_seccion_id.required'
                => 'Debes seleccionar un grupo.',

            'grupo_seccion_id.exists'
                => 'El grupo seleccionado no es válido.',

            'dia_id.required'
                => 'Debes seleccionar un día.',

            'dia_id.exists'
                => 'El día seleccionado no es válido.',

            'bloques_horarios.required'
                => 'Debes seleccionar al menos un bloque horario.',

            'bloques_horarios.array'
                => 'Los bloques seleccionados no son válidos.',

            'bloques_horarios.min'
                => 'Debes seleccionar al menos un bloque horario.',

            'bloques_horarios.*.exists'
                => 'Uno de los bloques seleccionados no es válido.',

            'bloques_horarios.*.distinct'
                => 'No puedes seleccionar el mismo bloque más de una vez.',

            'observaciones.max'
                => 'Las observaciones no pueden superar los 255 caracteres.',
        ]);

        $grupo = GrupoSeccion::with([
            'actividadSeccion.seccion',

            'profesores' => function ($query) {
                $query->where('estado', true);
            },

            'profesores.profesor',
        ])->findOrFail(
            $datos['grupo_seccion_id']
        );

        $seccion = $grupo
            ->actividadSeccion
            ->seccion;

        $periodoId = $seccion->periodo_academico_id;

        $version = $this->obtenerVersionSemestral(
            $periodoId
        );

        if (!$version) {
            throw ValidationException::withMessages([
                'horario'
                    => 'No existe una versión semestral actual para este período.',
            ]);
        }

        if ($grupo->profesores->isEmpty()) {
            throw ValidationException::withMessages([
                'horario'
                    => 'El grupo no tiene profesores asignados. Primero debes realizar la asignación de profesores.',
            ]);
        }

        $bloquesIds = collect(
            $datos['bloques_horarios']
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $bloquesSeleccionados = BloqueHorario::whereIn(
            'id',
            $bloquesIds
        )
            ->orderBy('numero')
            ->get()
            ->keyBy('id');

        $dia = Dia::findOrFail(
            $datos['dia_id']
        );

        foreach ($bloquesIds as $bloqueId) {
            $bloque = $bloquesSeleccionados->get(
                $bloqueId
            );

            $horarioDuplicado = Horario::where(
                'grupo_seccion_id',
                $grupo->id
            )
                ->where(
                    'version_planificacion_id',
                    $version->id
                )
                ->where(
                    'dia_id',
                    $datos['dia_id']
                )
                ->where(
                    'bloque_horario_id',
                    $bloqueId
                )
                ->where('estado', true)
                ->exists();

            if ($horarioDuplicado) {
                throw ValidationException::withMessages([
                    'horario'
                        => 'El grupo ya tiene asignado '
                        . $dia->nombre
                        . ', bloque '
                        . ($bloque?->numero ?? $bloqueId)
                        . '.',
                ]);
            }
        }

        foreach ($grupo->profesores as $grupoProfesor) {
            $profesor = $grupoProfesor->profesor;

            if (!$profesor) {
                continue;
            }

            $cantidadDisponibilidades = DisponibilidadProfesor::where(
                'profesor_id',
                $profesor->id
            )
                ->where(
                    'periodo_academico_id',
                    $periodoId
                )
                ->where('estado', true)
                ->count();

            foreach ($bloquesIds as $bloqueId) {
                $bloque = $bloquesSeleccionados->get(
                    $bloqueId
                );

                if ($cantidadDisponibilidades > 0) {
                    $estaDisponible = DisponibilidadProfesor::where(
                        'profesor_id',
                        $profesor->id
                    )
                        ->where(
                            'periodo_academico_id',
                            $periodoId
                        )
                        ->where(
                            'dia_id',
                            $datos['dia_id']
                        )
                        ->where(
                            'bloque_horario_id',
                            $bloqueId
                        )
                        ->where(
                            'tipo_disponibilidad',
                            'Disponible'
                        )
                        ->where('estado', true)
                        ->exists();

                    if (!$estaDisponible) {
                        throw ValidationException::withMessages([
                            'horario'
                                => 'El profesor '
                                . trim(
                                    $profesor->nombre
                                    . ' '
                                    . $profesor->apellido
                                )
                                . ' no tiene disponibilidad para '
                                . $dia->nombre
                                . ', bloque '
                                . ($bloque?->numero ?? $bloqueId)
                                . '.',
                        ]);
                    }
                }

                $conflictoProfesor = HorarioProfesor::where(
                    'profesor_id',
                    $profesor->id
                )
                    ->where('estado', true)
                    ->whereHas(
                        'horario',
                        function ($query) use (
                            $version,
                            $datos,
                            $bloqueId
                        ) {
                            $query
                                ->where(
                                    'version_planificacion_id',
                                    $version->id
                                )
                                ->where(
                                    'dia_id',
                                    $datos['dia_id']
                                )
                                ->where(
                                    'bloque_horario_id',
                                    $bloqueId
                                )
                                ->where('estado', true);
                        }
                    )
                    ->exists();

                if ($conflictoProfesor) {
                    throw ValidationException::withMessages([
                        'horario'
                            => 'El profesor '
                            . trim(
                                $profesor->nombre
                                . ' '
                                . $profesor->apellido
                            )
                            . ' ya tiene otro horario asignado el '
                            . $dia->nombre
                            . ' en el bloque '
                            . ($bloque?->numero ?? $bloqueId)
                            . '.',
                    ]);
                }
            }
        }

        DB::transaction(function () use (
            $datos,
            $grupo,
            $version,
            $bloquesIds
        ) {
            foreach ($bloquesIds as $bloqueId) {
                $horario = Horario::create([
                    'actividad_seccion_id'
                        => $grupo->actividad_seccion_id,

                    'grupo_seccion_id'
                        => $grupo->id,

                    'version_planificacion_id'
                        => $version->id,

                    'dia_id'
                        => $datos['dia_id'],

                    'bloque_horario_id'
                        => $bloqueId,

                    'observaciones'
                        => $datos['observaciones'] ?? null,

                    'estado'
                        => true,
                ]);

                foreach ($grupo->profesores as $grupoProfesor) {
                    HorarioProfesor::create([
                        'horario_id'
                            => $horario->id,

                        'profesor_id'
                            => $grupoProfesor->profesor_id,

                        'tipo_participacion'
                            => $grupoProfesor->tipo_participacion,

                        'es_principal'
                            => $grupoProfesor->es_principal,

                        'observaciones'
                            => $grupoProfesor->observaciones,

                        'estado'
                            => true,
                    ]);
                }
            }
        });

        $cantidadBloques = $bloquesIds->count();

        return redirect()
            ->route(
                'programacion.asignacion.horarios',
                [
                    'periodo_id'
                        => $periodoId,

                    'carrera_id'
                        => $request->integer('carrera_id') ?: null,

                    'semestre'
                        => $request->input('semestre'),

                    'seccion_id'
                        => $seccion->id,
                ]
            )
            ->with(
                'success',
                $cantidadBloques === 1
                    ? 'Horario asignado correctamente.'
                    : $cantidadBloques . ' bloques horarios asignados correctamente.'
            );
    }

    public function destroy(
        Request $request,
        Horario $horario
    ) {
        $horario->load(
            'grupoSeccion.actividadSeccion.seccion'
        );

        $seccion = $horario
            ->grupoSeccion
            ->actividadSeccion
            ->seccion;

        $version = $this->obtenerVersionSemestral(
            $seccion->periodo_academico_id
        );

        if (
            !$version
            || $horario->version_planificacion_id !== $version->id
        ) {
            throw ValidationException::withMessages([
                'horario'
                    => 'Solo se pueden eliminar horarios de la programación semestral actual.',
            ]);
        }

        $horario->delete();

        return redirect()
            ->route(
                'programacion.asignacion.horarios',
                [
                    'periodo_id'
                        => $seccion->periodo_academico_id,

                    'carrera_id'
                        => $request->integer('carrera_id') ?: null,

                    'semestre'
                        => $request->input('semestre'),

                    'seccion_id'
                        => $seccion->id,
                ]
            )
            ->with(
                'success',
                'Horario eliminado correctamente.'
            );
    }

    private function obtenerVersionSemestral(
        ?int $periodoId
    ) {
        if (!$periodoId) {
            return null;
        }

        return VersionPlanificacion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->where('tipo', 'semestral')
            ->where('es_actual', true)
            ->orderByDesc('id')
            ->first();
    }

    private function obtenerCarreras(
        ?int $periodoId
    ) {
        if (!$periodoId) {
            return collect();
        }

        $carreraIds = DB::table(
            'secciones_carreras as sc'
        )
            ->join(
                'secciones as s',
                's.id',
                '=',
                'sc.seccion_id'
            )
            ->where(
                's.periodo_academico_id',
                $periodoId
            )
            ->where(
                's.estado_activo',
                true
            )
            ->distinct()
            ->pluck('sc.carrera_id');

        return Carrera::where('estado', true)
            ->whereIn('id', $carreraIds)
            ->orderBy('nombre')
            ->get();
    }

    private function obtenerSemestres(
        ?int $periodoId,
        ?int $carreraId
    ) {
        if (!$periodoId) {
            return collect();
        }

        $query = Seccion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->where('estado_activo', true)
            ->whereNotNull('semestre');

        if ($carreraId) {
            $query->whereHas(
                'carreras',
                function ($consulta) use ($carreraId) {
                    $consulta->where(
                        'carreras.id',
                        $carreraId
                    );
                }
            );
        }

        return $query
            ->distinct()
            ->orderBy('semestre')
            ->pluck('semestre')
            ->values();
    }

    private function obtenerSecciones(
        ?int $periodoId,
        ?int $carreraId,
        $semestre
    ) {
        if (!$periodoId) {
            return collect();
        }

        $query = Seccion::with('asignatura')
            ->where(
                'periodo_academico_id',
                $periodoId
            )
            ->where(
                'estado_activo',
                true
            );

        if ($carreraId) {
            $query->whereHas(
                'carreras',
                function ($consulta) use ($carreraId) {
                    $consulta->where(
                        'carreras.id',
                        $carreraId
                    );
                }
            );
        }

        if (
            $semestre !== null
            && $semestre !== ''
        ) {
            $query->where(
                'semestre',
                $semestre
            );
        }

        return $query
            ->orderBy('codigo')
            ->get();
    }
}