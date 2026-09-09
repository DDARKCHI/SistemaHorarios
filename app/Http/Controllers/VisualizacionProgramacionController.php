<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Carrera;
use App\Models\Dia;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\Seccion;
use App\Models\VersionPlanificacion;
use Illuminate\Http\Request;

class VisualizacionProgramacionController extends Controller
{
    public function index(Request $request)
    {
        $periodos = PeriodoAcademico::orderByDesc('anio')
            ->orderByDesc('numero_semestre')
            ->get();

        $periodoId = $request->input(
            'periodo_id',
            $periodos->first()?->id
        );

        $versiones = VersionPlanificacion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->orderByDesc('id')
            ->get();

        $versionId = $request->input(
            'version_id',
            $versiones->first()?->id
        );

        $sedeId = $request->input('sede_id');
        $carreraId = $request->input('carrera_id');
        $semestre = $request->input('semestre');
        $seccionNumero = $request->input('seccion_numero');
        $asignaturaId = $request->input('asignatura_id');
        $profesorId = $request->input('profesor_id');

        $dias = Dia::where('estado', true)
            ->orderBy('numero')
            ->get();

        $bloques = BloqueHorario::where('estado', true)
            ->orderBy('numero')
            ->get();

        $sedes = Sede::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $carreras = $this->obtenerCarreras(
            $periodoId,
            $sedeId
        );

        $semestres = $this->obtenerSemestres(
            $periodoId,
            $sedeId,
            $carreraId
        );

        $secciones = $this->obtenerSecciones(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre
        );

        $asignaturas = $this->obtenerAsignaturas(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre,
            $seccionNumero
        );

        $profesores = $this->obtenerProfesores(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre,
            $seccionNumero,
            $asignaturaId
        );

        $horarios = collect();

        if ($versionId) {
            $horariosQuery = Horario::with([
                'actividadSeccion.seccion.asignatura',
                'actividadSeccion.seccion.carreras',
                'actividadSeccion.seccion.profesores.profesor',
                'grupoSeccion',
                'dia',
                'bloqueHorario',
                'profesores.profesor',
                'asignacionesSalas.sala',
                'asignacionesLaboratorios.laboratorio',
                'asignacionesLaboratoriosMoviles.laboratorioMovil',
            ])
                ->where('version_planificacion_id', $versionId)
                ->where('estado', true);

            if ($sedeId) {
                $horariosQuery->whereHas(
                    'actividadSeccion.seccion.carreras.sedes',
                    function ($query) use ($sedeId) {
                        $query->where(
                            'sedes.id',
                            $sedeId
                        );
                    }
                );
            }

            if ($carreraId) {
                $horariosQuery->whereHas(
                    'actividadSeccion.seccion.carreras',
                    function ($query) use ($carreraId) {
                        $query->where(
                            'carreras.id',
                            $carreraId
                        );
                    }
                );
            }

            if ($semestre !== null && $semestre !== '') {
                $horariosQuery->whereHas(
                    'actividadSeccion.seccion',
                    function ($query) use ($semestre) {
                        $query->where(
                            'secciones.semestre',
                            $semestre
                        );
                    }
                );
            }

            if ($seccionNumero) {
                $horariosQuery->whereHas(
                    'actividadSeccion.seccion',
                    function ($query) use ($seccionNumero) {
                        $query->where(
                            'secciones.codigo',
                            $seccionNumero
                        );
                    }
                );
            }

            if ($asignaturaId) {
                $horariosQuery->whereHas(
                    'actividadSeccion.seccion',
                    function ($query) use ($asignaturaId) {
                        $query->where(
                            'secciones.asignatura_id',
                            $asignaturaId
                        );
                    }
                );
            }

            if ($profesorId) {
                $horariosQuery->where(function ($query) use ($profesorId) {
                    $query->whereHas(
                        'profesores',
                        function ($profesorQuery) use ($profesorId) {
                            $profesorQuery->where(
                                'profesor_id',
                                $profesorId
                            );
                        }
                    )->orWhere(function ($subQuery) use ($profesorId) {
                        $subQuery
                            ->whereDoesntHave('profesores')
                            ->whereHas(
                                'actividadSeccion.seccion.profesores',
                                function ($profesorQuery) use ($profesorId) {
                                    $profesorQuery->where(
                                        'profesor_id',
                                        $profesorId
                                    );
                                }
                            );
                    });
                });
            }

            $horarios = $horariosQuery
                ->orderBy('dia_id')
                ->orderBy('bloque_horario_id')
                ->get();
        }

        $horariosPorBloque = $horarios->groupBy(function ($horario) {
            return $horario->dia_id . '-' . $horario->bloque_horario_id;
        });

        $diasSemana = $dias->filter(function ($dia) {
            return $dia->numero >= 1 && $dia->numero <= 5;
        })->values();

        $sabado = $dias->first(function ($dia) {
            return $dia->numero == 6;
        });

        $tieneProgramacionSabado = $sabado
            ? $horarios->contains('dia_id', $sabado->id)
            : false;

        $diasVisualizacion = $diasSemana;

        if ($sabado && $tieneProgramacionSabado) {
            $diasVisualizacion = $diasVisualizacion
                ->push($sabado)
                ->values();
        }

        return view('programacion.index', [
            'periodos' => $periodos,
            'versiones' => $versiones,
            'sedes' => $sedes,
            'carreras' => $carreras,
            'semestres' => $semestres,
            'secciones' => $secciones,
            'asignaturas' => $asignaturas,
            'profesores' => $profesores,
            'dias' => $dias,
            'diasVisualizacion' => $diasVisualizacion,
            'bloques' => $bloques,
            'horarios' => $horarios,
            'horariosPorBloque' => $horariosPorBloque,
            'periodoId' => $periodoId,
            'versionId' => $versionId,
            'sedeId' => $sedeId,
            'carreraId' => $carreraId,
            'semestre' => $semestre,
            'seccionNumero' => $seccionNumero,
            'asignaturaId' => $asignaturaId,
            'profesorId' => $profesorId,
        ]);
    }

    public function filtros(Request $request)
    {
        $periodoId = $request->input('periodo_id');
        $sedeId = $request->input('sede_id');
        $carreraId = $request->input('carrera_id');
        $semestre = $request->input('semestre');
        $seccionNumero = $request->input('seccion_numero');
        $asignaturaId = $request->input('asignatura_id');

        $carreras = $this->obtenerCarreras(
            $periodoId,
            $sedeId
        );

        $semestres = $this->obtenerSemestres(
            $periodoId,
            $sedeId,
            $carreraId
        );

        $secciones = $this->obtenerSecciones(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre
        );

        $asignaturas = $this->obtenerAsignaturas(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre,
            $seccionNumero
        );

        $profesores = $this->obtenerProfesores(
            $periodoId,
            $sedeId,
            $carreraId,
            $semestre,
            $seccionNumero,
            $asignaturaId
        );

        return response()->json([
            'carreras' => $carreras->map(function ($carrera) {
                return [
                    'id' => $carrera->id,
                    'nombre' => $carrera->nombre,
                ];
            })->values(),

            'semestres' => $semestres
                ->map(fn ($valor) => (int) $valor)
                ->values(),

            'secciones' => $secciones
                ->pluck('codigo')
                ->unique()
                ->sort()
                ->values(),

            'asignaturas' => $asignaturas->map(function ($asignatura) {
                return [
                    'id' => $asignatura->id,
                    'codigo' => $asignatura->codigo,
                    'nombre' => $asignatura->nombre,
                ];
            })->values(),

            'profesores' => $profesores->map(function ($profesor) {
                return [
                    'id' => $profesor->id,
                    'nombre' => trim(
                        $profesor->nombre . ' ' . $profesor->apellido
                    ),
                ];
            })->values(),
        ]);
    }

    private function obtenerCarreras($periodoId, $sedeId = null)
    {
        $query = Carrera::where('estado', true)
            ->whereHas(
                'secciones',
                function ($query) use ($periodoId) {
                    $query->where(
                        'secciones.periodo_academico_id',
                        $periodoId
                    );
                }
            )
            ->orderBy('nombre');

        if ($sedeId) {
            $query->whereHas(
                'sedes',
                function ($query) use ($sedeId) {
                    $query->where(
                        'sedes.id',
                        $sedeId
                    );
                }
            );
        }

        return $query->get();
    }

    private function obtenerSemestres(
        $periodoId,
        $sedeId = null,
        $carreraId = null
    ) {
        $query = Seccion::query()
            ->where(
                'periodo_academico_id',
                $periodoId
            )
            ->whereNotNull('semestre')
            ->select('semestre')
            ->distinct()
            ->orderBy('semestre');

        if ($sedeId) {
            $query->whereHas(
                'carreras.sedes',
                function ($query) use ($sedeId) {
                    $query->where(
                        'sedes.id',
                        $sedeId
                    );
                }
            );
        }

        if ($carreraId) {
            $query->whereHas(
                'carreras',
                function ($query) use ($carreraId) {
                    $query->where(
                        'carreras.id',
                        $carreraId
                    );
                }
            );
        }

        return $query
            ->pluck('semestre')
            ->map(fn ($valor) => (int) $valor);
    }

    private function obtenerSecciones(
        $periodoId,
        $sedeId = null,
        $carreraId = null,
        $semestre = null
    ) {
        $query = Seccion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('codigo');

        if ($sedeId) {
            $query->whereHas(
                'carreras.sedes',
                function ($query) use ($sedeId) {
                    $query->where(
                        'sedes.id',
                        $sedeId
                    );
                }
            );
        }

        if ($carreraId) {
            $query->whereHas(
                'carreras',
                function ($query) use ($carreraId) {
                    $query->where(
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

        return $query->get();
    }

    private function obtenerAsignaturas(
        $periodoId,
        $sedeId = null,
        $carreraId = null,
        $semestre = null,
        $seccionNumero = null
    ) {
        $query = Asignatura::query()
            ->orderBy('codigo');

        $query->whereHas(
            'secciones',
            function ($query) use (
                $periodoId,
                $sedeId,
                $carreraId,
                $semestre,
                $seccionNumero
            ) {
                $query->where(
                    'secciones.periodo_academico_id',
                    $periodoId
                );

                if ($seccionNumero) {
                    $query->where(
                        'secciones.codigo',
                        $seccionNumero
                    );
                }

                if ($semestre !== null && $semestre !== '') {
                    $query->where(
                        'secciones.semestre',
                        $semestre
                    );
                }

                if ($sedeId) {
                    $query->whereHas(
                        'carreras.sedes',
                        function ($careerQuery) use ($sedeId) {
                            $careerQuery->where(
                                'sedes.id',
                                $sedeId
                            );
                        }
                    );
                }

                if ($carreraId) {
                    $query->whereHas(
                        'carreras',
                        function ($careerQuery) use ($carreraId) {
                            $careerQuery->where(
                                'carreras.id',
                                $carreraId
                            );
                        }
                    );
                }
            }
        );

        return $query->get();
    }

    private function obtenerProfesores(
        $periodoId,
        $sedeId = null,
        $carreraId = null,
        $semestre = null,
        $seccionNumero = null,
        $asignaturaId = null
    ) {
        $query = Profesor::query()
            ->where('estado', true)
            ->orderBy('apellido')
            ->orderBy('nombre');

        $query->whereHas(
            'secciones',
            function ($query) use (
                $periodoId,
                $sedeId,
                $carreraId,
                $semestre,
                $seccionNumero,
                $asignaturaId
            ) {
                $query->whereHas(
                    'seccion',
                    function ($seccionQuery) use (
                        $periodoId,
                        $sedeId,
                        $carreraId,
                        $semestre,
                        $seccionNumero,
                        $asignaturaId
                    ) {
                        $seccionQuery->where(
                            'periodo_academico_id',
                            $periodoId
                        );

                        if ($seccionNumero) {
                            $seccionQuery->where(
                                'secciones.codigo',
                                $seccionNumero
                            );
                        }

                        if ($semestre !== null && $semestre !== '') {
                            $seccionQuery->where(
                                'secciones.semestre',
                                $semestre
                            );
                        }

                        if ($asignaturaId) {
                            $seccionQuery->where(
                                'asignatura_id',
                                $asignaturaId
                            );
                        }

                        if ($sedeId) {
                            $seccionQuery->whereHas(
                                'carreras.sedes',
                                function ($careerQuery) use ($sedeId) {
                                    $careerQuery->where(
                                        'sedes.id',
                                        $sedeId
                                    );
                                }
                            );
                        }

                        if ($carreraId) {
                            $seccionQuery->whereHas(
                                'carreras',
                                function ($careerQuery) use ($carreraId) {
                                    $careerQuery->where(
                                        'carreras.id',
                                        $carreraId
                                    );
                                }
                            );
                        }
                    }
                );
            }
        );

        return $query->get();
    }
}