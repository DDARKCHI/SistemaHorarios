<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Carrera;
use App\Models\Dia;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\Sede;
use App\Models\Seccion;
use App\Models\VersionPlanificacion;
use App\Services\GeneracionAutomatica;
use App\Support\DiasCalendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProgramacionSemestralController extends Controller
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
            ->where('tipo', 'semestral')
            ->orderByDesc('id')
            ->get();

        $versionesAnteriores = VersionPlanificacion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->where('tipo', 'anterior')
            ->orderByDesc('id')
            ->get();
        $versionesBase = $versiones->concat($versionesAnteriores);

        $versionIdSolicitada = $request->input('version_id');

        $version = $versiones
            ->firstWhere(
                'id',
                (int) $versionIdSolicitada
            )
            ?? $versiones->firstWhere('es_actual', true)
            ?? $versiones->first();
        $versionId = $version?->id;

        $sedeId = $request->input('sede_id');
        $carreraId = $request->input('carrera_id');
        $semestre = $request->input('semestre');
        $seccionNumero = $request->input('seccion_numero');
        $asignaturaId = $request->input('asignatura_id');
        $profesorId = $request->input('profesor_id');

        $hayFiltroAplicado =
            !empty($sedeId) ||
            !empty($carreraId) ||
            ($semestre !== null && $semestre !== '') ||
            !empty($seccionNumero) ||
            !empty($asignaturaId) ||
            !empty($profesorId);

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
            $version,
            $sedeId,
            $carreraId,
            $semestre,
            $seccionNumero,
            $asignaturaId
        );

        $horarios = collect();

        if ($version && $hayFiltroAplicado) {
            $horariosQuery = $this->consultaHorarios($versionId, $sedeId, $carreraId, $semestre, $seccionNumero, $asignaturaId)
                ->with([
                    'actividadSeccion.seccion.asignatura',
                    'actividadSeccion.seccion.carreras',
                    'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true),
                    'actividadSeccion.seccion.profesores.profesor',
                    'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true),
                    'grupoSeccion.profesores.profesor',
                    'dia',
                    'bloqueHorario',
                    'profesores' => fn ($q) => $q->where('estado', true),
                    'profesores.profesor',
                    'asignacionesSalas' => fn ($q) => $q->where('estado', true),
                    'asignacionesSalas.sala',
                    'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true),
                    'asignacionesLaboratorios.laboratorio',
                    'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true),
                    'asignacionesLaboratoriosMoviles.laboratorioMovil',
                ]);

            if ($profesorId) {
                $horariosQuery = $this->unirDocentesEfectivos($horariosQuery)
                    ->whereRaw(self::DOCENTE_EFECTIVO.' = ?', [(int) $profesorId])
                    ->select('horarios.*')->distinct();
            }

            $horarios = $horariosQuery->orderBy('horarios.dia_id')->orderBy('horarios.bloque_horario_id')->get();

            $this->aplicarDatosProfesoresVersion(
                $horarios,
                $version
            );
        }

        $horariosPorBloque = $horarios->groupBy(function ($horario) {
            return $horario->dia_id . '-' . $horario->bloque_horario_id;
        });

        $diasVisualizacion = DiasCalendario::conClases(
            $dias->filter(fn ($dia) => $dia->numero >= 1 && $dia->numero <= 6), $horarios
        );

        return view('programacion.semestral.index', [
            'periodos' => $periodos,
            'versiones' => $versiones,
            'versionesAnteriores' => $versionesAnteriores,
            'versionesBase' => $versionesBase,
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
            'hayFiltroAplicado' => $hayFiltroAplicado,
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

    public function generar(
        Request $request,
        GeneracionAutomatica $service
    ) {
        $datos = $request->validate([
            'version_base_id' => ['required_without:version_anterior_id', 'integer', 'exists:versiones_planificacion,id'],
            'version_anterior_id' => ['required_without:version_base_id', 'integer', 'exists:versiones_planificacion,id'],
            'solicitud' => ['nullable', 'uuid'],
        ]);

        $versionBase = VersionPlanificacion::findOrFail($datos['version_base_id'] ?? $datos['version_anterior_id']);

        $versionSemestral = $service->generar(
            $versionBase,
            $request->user()->id,
            $request->ip(),
            $datos['solicitud'] ?? null
        );

        return redirect()
            ->route('programacion.semestral', [
                'periodo_id' => $versionSemestral->periodo_academico_id,
                'version_id' => $versionSemestral->id,
            ])
            ->with(
                'success',
                'Programación generada como borrador V'.$versionSemestral->version
                .'. Revisa el resultado y actívalo desde Más acciones.'
            );
    }

    public function filtros(Request $request)
    {
        $periodoId = $request->input('periodo_id');
        $versionId = $request->input('version_id');
        $sedeId = $request->input('sede_id');
        $carreraId = $request->input('carrera_id');
        $semestre = $request->input('semestre');
        $seccionNumero = $request->input('seccion_numero');
        $asignaturaId = $request->input('asignatura_id');

        $versiones = VersionPlanificacion::where('tipo', 'semestral')
            ->when($periodoId, fn ($consulta) => $consulta->where('periodo_academico_id', $periodoId));
        $version = $versionId
            ? $versiones->find($versionId)
            : ($periodoId ? $versiones->where('es_actual', true)->orderByDesc('id')->first() : null);

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
            $version,
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

    private const DOCENTE_EFECTIVO = 'COALESCE(hp.profesor_id, gp.profesor_id, sp.profesor_id)';

    private function consultaHorarios($versionId, $sedeId, $carreraId, $semestre, $seccionNumero, $asignaturaId): Builder
    {
        $consulta = Horario::query()->where('horarios.version_planificacion_id', $versionId)->where('horarios.estado', true);

        if ($sedeId || $carreraId || $semestre !== null && $semestre !== '' || $seccionNumero || $asignaturaId) {
            $consulta->whereHas('actividadSeccion.seccion', function ($seccion) use ($sedeId, $carreraId, $semestre, $seccionNumero, $asignaturaId) {
                if ($sedeId) $seccion->whereHas('carreras.sedes', fn ($s) => $s->where('sedes.id', $sedeId));
                if ($carreraId) $seccion->whereHas('carreras', fn ($c) => $c->where('carreras.id', $carreraId));
                if ($semestre !== null && $semestre !== '') $seccion->where('secciones.semestre', $semestre);
                if ($seccionNumero) $seccion->where('secciones.codigo', $seccionNumero);
                if ($asignaturaId) $seccion->where('secciones.asignatura_id', $asignaturaId);
            });
        }

        return $consulta;
    }

    private function unirDocentesEfectivos(Builder $consulta): Builder
    {
        // Una asignación activa de módulo prevalece sobre el grupo y la sección.
        return $consulta->join('actividades_seccion as actividad', 'actividad.id', '=', 'horarios.actividad_seccion_id')
            ->leftJoin('horarios_profesores as hp', fn ($j) => $j->on('hp.horario_id', '=', 'horarios.id')->where('hp.estado', true))
            ->leftJoin('grupos_profesores as gp', fn ($j) => $j->on('gp.grupo_seccion_id', '=', 'horarios.grupo_seccion_id')->where('gp.estado', true)->whereNull('hp.id'))
            ->leftJoin('secciones_profesores as sp', fn ($j) => $j->on('sp.seccion_id', '=', 'actividad.seccion_id')->where('sp.estado', true)->whereNull('hp.id')->whereNull('gp.id'));
    }

    private function obtenerProfesores(?VersionPlanificacion $version, $sedeId = null, $carreraId = null, $semestre = null, $seccionNumero = null, $asignaturaId = null)
    {
        if (! $version) return collect();

        $horarios = $this->consultaHorarios($version->id, $sedeId, $carreraId, $semestre, $seccionNumero, $asignaturaId);
        $docentes = $this->unirDocentesEfectivos($horarios)->selectRaw(self::DOCENTE_EFECTIVO);
        $profesores = Profesor::where('estado', true)->whereIn('id', $docentes)->get();

        $datosVersion = $this->datosProfesoresVersion($version, $profesores->modelKeys());

        foreach ($profesores as $profesor) {
            $datos = $datosVersion->get($profesor->id);

            if ($datos) {
                $this->aplicarDatosProfesor(
                    $profesor,
                    $datos
                );
            }
        }

        return $profesores
            ->sortBy(function ($profesor) {
                return mb_strtoupper(
                    trim(
                        $profesor->apellido . ' ' . $profesor->nombre
                    )
                );
            })
            ->values();
    }

    private function aplicarDatosProfesoresVersion($horarios, VersionPlanificacion $version): void
    {
        $asignaciones = $horarios->flatMap(function (Horario $horario) {
            $docentes = $horario->profesores;
            if ($docentes->isEmpty()) $docentes = $horario->grupoSeccion?->profesores ?? collect();
            if ($docentes->isEmpty()) $docentes = $horario->actividadSeccion?->seccion?->profesores ?? collect();

            $docentes = $docentes->unique('profesor_id')->values();
            $horario->setRelation('docentesEfectivos', $docentes);

            return $docentes;
        });

        $datosVersion = $this->datosProfesoresVersion($version, $asignaciones->pluck('profesor_id')->unique()->all());

        foreach ($asignaciones as $asignacion) {
            $datos = $datosVersion->get($asignacion->profesor_id);
            if ($datos && $asignacion->profesor) $this->aplicarDatosProfesor($asignacion->profesor, $datos);
        }
    }

    private function datosProfesoresVersion(VersionPlanificacion $version, array $profesorIds): \Illuminate\Support\Collection
    {
        if (! $profesorIds || ($version->es_actual && $version->estado === 'Borrador')) return collect();

        return ProfesorVersion::where('version_planificacion_id', $version->id)
            ->whereIn('profesor_id', $profesorIds)->get()->keyBy('profesor_id');
    }

    private function aplicarDatosProfesor(
        Profesor $profesor,
        ProfesorVersion $datos
    ): void {
        $profesor->nombre = $datos->nombre;
        $profesor->apellido = $datos->apellido;
        $profesor->rut = $datos->rut;
        $profesor->correo_institucional = $datos->correo_institucional;
        $profesor->correo_personal = $datos->correo_personal;
        $profesor->tipo_contrato = $datos->tipo_contrato;
        $profesor->tipo_profesor = $datos->tipo_profesor;
        $profesor->unidad_academica = $datos->unidad_academica;
    }
}
