<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignacionProfesorController extends Controller
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

        $seccion = null;
        $profesoresSugeridos = collect();

        if ($seccionId && $seccionesFiltro->contains('id', $seccionId)) {
            $seccion = Seccion::with([
                'asignatura',
                'carreras',
                'profesores.profesor',
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
            ])
                ->where('id', $seccionId)
                ->where('periodo_academico_id', $periodoId)
                ->where('estado_activo', true)
                ->first();

            if ($seccion) {
                $profesoresSeccion = $seccion->profesores
                    ->where('estado', true)
                    ->pluck('profesor')
                    ->filter();

                $profesoresGrupos = $seccion->actividades
                    ->flatMap(function ($actividad) {
                        return $actividad->grupos;
                    })
                    ->flatMap(function ($grupo) {
                        return $grupo->profesores;
                    })
                    ->where('estado', true)
                    ->pluck('profesor')
                    ->filter();

                $profesoresSugeridos = $profesoresSeccion
                    ->merge($profesoresGrupos)
                    ->unique('id')
                    ->sortBy(function ($profesor) {
                        return $profesor->nombre . ' ' . $profesor->apellido;
                    })
                    ->values();
            }
        }

        return view(
            'programacion.asignacion-profesores',
            compact(
                'periodos',
                'periodoId',
                'carreras',
                'carreraId',
                'semestres',
                'semestre',
                'seccionesFiltro',
                'seccionId',
                'seccion',
                'profesoresSugeridos'
            )
        );
    }

    public function filtros(Request $request)
    {
        if ($request->boolean('buscar_profesores')) {
            return $this->buscarProfesores($request);
        }

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
                $codigoAsignatura = $seccion->asignatura?->codigo
                    ?? 'Sin código';

                $nombreAsignatura = $seccion->asignatura?->nombre
                    ?? $seccion->nombre
                    ?? 'Sin nombre';

                return [
                    'id' => $seccion->id,
                    'texto' => $seccion->codigo
                        . ' — '
                        . $codigoAsignatura
                        . ' '
                        . $nombreAsignatura,
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
            'profesor_id' => [
                'required',
                'exists:profesores,id',
            ],
            'tipo_participacion' => [
                'required',
                Rule::in([
                    'PROFESOR RESPONSABLE',
                    'PROFESOR COLABORADOR',
                    'AYUDANTE',
                ]),
            ],
            'es_principal' => [
                'nullable',
                'boolean',
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'grupo_seccion_id.required' => 'Debes seleccionar un grupo.',
            'grupo_seccion_id.exists' => 'El grupo seleccionado no es válido.',
            'profesor_id.required' => 'Debes seleccionar un profesor.',
            'profesor_id.exists' => 'El profesor seleccionado no es válido.',
            'tipo_participacion.required' => 'Debes seleccionar un tipo de participación.',
            'tipo_participacion.in' => 'El tipo de participación seleccionado no es válido.',
            'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
        ]);

        $grupo = GrupoSeccion::with(
            'actividadSeccion.seccion.asignatura'
        )->findOrFail(
            $datos['grupo_seccion_id']
        );

        $profesor = Profesor::where('estado', true)
            ->findOrFail($datos['profesor_id']);

        $universidadAsignatura = $grupo
            ->actividadSeccion
            ?->seccion
            ?->asignatura
            ?->universidad_id;

        if (
            $universidadAsignatura
            && $profesor->universidad_id != $universidadAsignatura
        ) {
            throw ValidationException::withMessages([
                'profesor_id' => 'El profesor seleccionado no pertenece a la misma institución de la asignatura.',
            ]);
        }

        $esPrincipal = $request->boolean('es_principal');

        DB::transaction(function () use (
            $datos,
            $grupo,
            $esPrincipal
        ) {
            if ($esPrincipal) {
                GrupoProfesor::where(
                    'grupo_seccion_id',
                    $grupo->id
                )->update([
                    'es_principal' => false,
                ]);
            }

            GrupoProfesor::updateOrCreate(
                [
                    'grupo_seccion_id' => $grupo->id,
                    'profesor_id' => $datos['profesor_id'],
                ],
                [
                    'tipo_participacion' => $datos['tipo_participacion'],
                    'es_principal' => $esPrincipal,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'estado' => true,
                ]
            );
        });

        return redirect()
            ->route(
                'programacion.asignacion.profesores',
                [
                    'periodo_id' => $request->integer('periodo_id'),
                    'carrera_id' => $request->integer('carrera_id') ?: null,
                    'semestre' => $request->input('semestre'),
                    'seccion_id' => $grupo->actividadSeccion->seccion_id,
                ]
            )
            ->with(
                'success',
                'Profesor asignado al grupo correctamente.'
            );
    }

    public function destroy(
        Request $request,
        GrupoProfesor $asignacion
    ) {
        $asignacion->load(
            'grupoSeccion.actividadSeccion'
        );

        $seccionId = $asignacion
            ->grupoSeccion
            ->actividadSeccion
            ->seccion_id;

        $asignacion->delete();

        return redirect()
            ->route(
                'programacion.asignacion.profesores',
                [
                    'periodo_id' => $request->integer('periodo_id'),
                    'carrera_id' => $request->integer('carrera_id') ?: null,
                    'semestre' => $request->input('semestre'),
                    'seccion_id' => $seccionId,
                ]
            )
            ->with(
                'success',
                'Asignación del profesor eliminada correctamente.'
            );
    }

    private function buscarProfesores(Request $request)
    {
        $seccionId = $request->integer('seccion_id');
        $termino = trim((string) $request->input('q'));

        if (!$seccionId || mb_strlen($termino) < 2) {
            return response()->json([
                'profesores' => [],
            ]);
        }

        $seccion = Seccion::with([
            'asignatura',
            'profesores',
        ])->find($seccionId);

        if (!$seccion) {
            return response()->json([
                'profesores' => [],
            ]);
        }

        $universidadId = $seccion->asignatura?->universidad_id;

        $profesoresRelacionados = $seccion->profesores
            ->where('estado', true)
            ->pluck('profesor_id')
            ->all();

        $query = Profesor::where('estado', true);

        if ($universidadId) {
            $query->where(
                'universidad_id',
                $universidadId
            );
        }

        $query->where(function ($consulta) use ($termino) {
            $consulta
                ->where('nombre', 'like', '%' . $termino . '%')
                ->orWhere('apellido', 'like', '%' . $termino . '%')
                ->orWhere('rut', 'like', '%' . $termino . '%')
                ->orWhere('correo_institucional', 'like', '%' . $termino . '%');
        });

        $profesores = $query
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->limit(20)
            ->get()
            ->map(function ($profesor) use ($profesoresRelacionados) {
                return [
                    'id' => $profesor->id,
                    'nombre' => trim(
                        $profesor->nombre
                        . ' '
                        . $profesor->apellido
                    ),
                    'rut' => $profesor->rut,
                    'tipo_profesor' => $profesor->tipo_profesor,
                    'relacionado' => in_array(
                        $profesor->id,
                        $profesoresRelacionados
                    ),
                ];
            })
            ->values();

        return response()->json([
            'profesores' => $profesores,
        ]);
    }

    private function obtenerCarreras(?int $periodoId)
    {
        if (!$periodoId) {
            return collect();
        }

        $carreraIds = DB::table('secciones_carreras as sc')
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
            ->where('estado_activo', true);

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

        if ($semestre !== null && $semestre !== '') {
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