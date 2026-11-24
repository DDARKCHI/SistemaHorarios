<?php

namespace App\Http\Controllers;

use App\Models\BloqueHorario;
use App\Models\Carrera;
use App\Models\Dia;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Seccion;
use App\Models\VersionPlanificacion;
use App\Models\Profesor;
use App\Services\AsignacionModulosHorario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $editable = $version && $version->estado === 'Borrador' && $periodos->contains('id', $periodoId);

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
                'actividades.grupos.horarios.profesores' => fn ($q) => $q->where('estado', true),
                'actividades.grupos.horarios.profesores.profesor',
                'actividades.grupos.horarios.profesores.profesor.disponibilidades' => fn ($q) => $q->where('periodo_academico_id', $periodoId)->where('estado', true),
            ])
                ->where('id', $seccionId)
                ->where(
                    'periodo_academico_id',
                    $periodoId
                )
                ->where('estado_activo', true)
                ->first();
        }

        $profesoresParaHorario = $seccion && $editable
            ? Profesor::where('universidad_id', $seccion->asignatura->universidad_id)->where('estado', true)->orderBy('apellido')->orderBy('nombre')->get(['id', 'nombre', 'apellido', 'rut'])
            : collect();
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
                'editable',
                'dias',
                'bloques',
                'seccion',
                'profesoresParaHorario'
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

    public function store(Request $request, AsignacionModulosHorario $asignador)
    {
        $datos = $request->validate([
            'grupo_seccion_id' => ['required', 'integer', 'exists:grupos_seccion,id'],
            'version_id' => ['required', 'integer', 'min:1'],
            'dia_id' => ['required', 'integer', 'exists:dias,id'],
            'profesores_ids' => ['required', 'array', 'min:1', 'max:100'],
            'profesores_ids.*' => ['required', 'integer', 'distinct', 'exists:profesores,id'],
            'bloques_horarios' => ['required', 'array', 'min:1', 'max:255'],
            'bloques_horarios.*' => ['required', 'integer', 'distinct', 'exists:bloques_horarios,id'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'grupo_seccion_id.*' => 'Selecciona un grupo válido.',
            'version_id.*' => 'Vuelve a abrir la asignación para confirmar la versión semestral vigente.',
            'dia_id.*' => 'Selecciona un día válido.',
            'profesores_ids.*' => 'Selecciona al menos un docente del grupo (hasta 100).',
            'profesores_ids.*.*' => 'Los docentes deben ser válidos y no repetirse.',
            'bloques_horarios.*' => 'Selecciona al menos un módulo (hasta 255).',
            'bloques_horarios.*.*' => 'Los módulos deben ser válidos y no repetirse.',
            'observaciones.*' => 'Las observaciones admiten hasta 255 caracteres.',
        ]);
        $resultado = $asignador->guardar($datos, $request->user()->id, $request->ip());
        return redirect()->route('programacion.asignacion.horarios', [
            'periodo_id' => $resultado['periodo_id'], 'seccion_id' => $resultado['seccion_id'],
            'carrera_id' => $request->integer('carrera_id') ?: null, 'semestre' => $request->integer('semestre') ?: null,
        ])->with('success', $resultado['cantidad'] === 1 ? 'Horario asignado correctamente.' : $resultado['cantidad'].' módulos horarios asignados correctamente.');
    }

    public function destroy(Request $request, Horario $horario, AsignacionModulosHorario $asignador)
    {
        $contexto = $asignador->eliminar($horario, $request->user()->id, $request->ip());
        return redirect()->route('programacion.asignacion.horarios', $contexto + [
            'carrera_id' => $request->integer('carrera_id') ?: null, 'semestre' => $request->integer('semestre') ?: null,
        ])->with('success', 'Horario eliminado correctamente.');
    }

    public function asignarProfesor(Request $request, Horario $horario, AsignacionModulosHorario $asignador)
    {
        $datos = $request->validate([
            'version_id' => ['required', 'integer', 'exists:versiones_planificacion,id'],
            'profesor_id' => ['required', 'integer', 'exists:profesores,id'],
            'accion_docente' => ['required', 'in:reemplazar,agregar'],
            'huella_profesores' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ], ['profesor_id.*' => 'Selecciona un docente registrado.', 'accion_docente.*' => 'Selecciona cómo asignar el docente.',
            'version_id.*' => 'Actualiza la página para revisar la versión vigente.', 'huella_profesores.*' => 'Actualiza la página antes de modificar los docentes.']);
        $resultado = $asignador->asignarProfesor($horario, $datos, $request->user()->id, $request->ip());
        return redirect()->route('programacion.asignacion.horarios', ['periodo_id' => $resultado['periodo_id'], 'seccion_id' => $resultado['seccion_id'],
            'carrera_id' => $request->integer('carrera_id') ?: null, 'semestre' => $request->integer('semestre') ?: null])
            ->with('success', 'Docente asignado al horario correctamente.'.($resultado['sin_declaracion'] ? ' El docente no ha informado disponibilidad para este período.' : ''));
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
