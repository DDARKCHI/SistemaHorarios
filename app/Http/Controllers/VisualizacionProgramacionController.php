<?php

namespace App\Http\Controllers;

use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\Horario;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Services\FiltrosProgramacionAnterior;
use App\Support\DiasCalendario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VisualizacionProgramacionController extends Controller
{
    public function index(Request $request)
    {
        $filtros = new FiltrosProgramacionAnterior;
        $seleccion = $filtros->contexto($this->validarFiltros($request));
        $opciones = $filtros->opciones($seleccion);
        $hayFiltroAplicado = collect($seleccion)->only(FiltrosProgramacionAnterior::ACADEMICOS)
            ->contains(fn ($valor) => filled($valor));
        $dias = Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = BloqueHorario::where('estado', true)->orderBy('numero')->get();
        $horarios = collect();

        if ($seleccion['version_id'] && $hayFiltroAplicado) {
            $horarios = Horario::with([
                'actividadSeccion.seccion.asignatura', 'actividadSeccion.seccion.carreras',
                'actividadSeccion.seccion.profesores' => fn ($query) => $query->where('estado', true),
                'actividadSeccion.seccion.profesores.profesor', 'grupoSeccion', 'dia', 'bloqueHorario',
                'profesores' => fn ($query) => $query->where('estado', true), 'profesores.profesor',
                'asignacionesSalas.sala', 'asignacionesLaboratorios.laboratorio',
                'asignacionesLaboratoriosMoviles.laboratorioMovil',
            ])->whereIn('horarios.id', $filtros->consulta($seleccion)->select('h.id'))
                ->orderBy('dia_id')->orderBy('bloque_horario_id')->get();
            $this->aplicarDatosProfesoresVersion($horarios, $seleccion['version_id']);
        }

        $horariosPorBloque = $horarios->groupBy(fn ($horario) => $horario->dia_id.'-'.$horario->bloque_horario_id);
        $diasVisualizacion = DiasCalendario::conClases(
            $dias->filter(fn ($dia) => $dia->numero >= 1 && $dia->numero <= 6), $horarios
        );

        return view('programacion.index', $opciones + [
            'dias' => $dias, 'diasVisualizacion' => $diasVisualizacion, 'bloques' => $bloques,
            'horarios' => $horarios, 'horariosPorBloque' => $horariosPorBloque,
            'periodoId' => $seleccion['periodo_id'], 'versionId' => $seleccion['version_id'],
            'sedeId' => $seleccion['sede_id'], 'carreraId' => $seleccion['carrera_id'],
            'semestre' => $seleccion['semestre'], 'seccionNumero' => $seleccion['seccion_numero'],
            'asignaturaId' => $seleccion['asignatura_id'], 'profesorId' => $seleccion['profesor_id'],
            'hayFiltroAplicado' => $hayFiltroAplicado,
        ]);
    }

    public function filtros(Request $request)
    {
        $entrada = $this->validarFiltros($request);
        $filtros = new FiltrosProgramacionAnterior;
        $seleccion = $filtros->contexto($entrada);
        $seleccion = $filtros->reconciliar($seleccion, $entrada['cambiado'] ?? null, $entrada['prioridad'] ?? []);
        $opciones = $filtros->opciones($seleccion);

        return response()->json([
            'seleccionados' => $seleccion,
            'periodos' => $opciones['periodos']->map->only(['id', 'anio', 'numero_semestre'])->values(),
            'versiones' => $opciones['versiones']->map->only(['id', 'version', 'es_actual'])->values(),
            'sedes' => $opciones['sedes']->values(), 'carreras' => $opciones['carreras']->values(),
            'semestres' => $opciones['semestres']->values(),
            'secciones' => $opciones['secciones']->pluck('codigo')->values(),
            'asignaturas' => $opciones['asignaturas']->values(),
            'profesores' => $opciones['profesores']->map(fn ($profesor) => [
                'id' => $profesor->id, 'nombre' => trim($profesor->nombre.' '.$profesor->apellido),
            ])->values(),
        ]);
    }

    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'periodo_id' => ['nullable', 'integer', 'min:1'], 'version_id' => ['nullable', 'integer', 'min:1'],
            'sede_id' => ['nullable', 'integer', 'min:1'], 'carrera_id' => ['nullable', 'integer', 'min:1'],
            'semestre' => ['nullable', 'integer', 'min:0'], 'seccion_numero' => ['nullable', 'string', 'max:30'],
            'asignatura_id' => ['nullable', 'integer', 'min:1'], 'profesor_id' => ['nullable', 'integer', 'min:1'],
            'cambiado' => ['nullable', Rule::in(FiltrosProgramacionAnterior::CAMPOS)],
            'prioridad' => ['sometimes', 'array', 'max:6'],
            'prioridad.*' => ['string', 'distinct', Rule::in(FiltrosProgramacionAnterior::ACADEMICOS)],
        ]);
    }

    private function aplicarDatosProfesoresVersion(
        $horarios,
        $versionId
    ): void {
        if (!$versionId || $horarios->isEmpty()) {
            return;
        }

        $profesorIds = collect();

        foreach ($horarios as $horario) {
            foreach ($horario->profesores as $horarioProfesor) {
                if ($horarioProfesor->profesor_id) {
                    $profesorIds->push(
                        $horarioProfesor->profesor_id
                    );
                }
            }

            $seccion = $horario
                ->actividadSeccion
                ?->seccion;

            if ($seccion) {
                foreach ($seccion->profesores as $seccionProfesor) {
                    if ($seccionProfesor->profesor_id) {
                        $profesorIds->push(
                            $seccionProfesor->profesor_id
                        );
                    }
                }
            }
        }

        $profesorIds = $profesorIds
            ->unique()
            ->values();

        if ($profesorIds->isEmpty()) {
            return;
        }

        $datosVersion = ProfesorVersion::where(
            'version_planificacion_id',
            $versionId
        )
            ->whereIn(
                'profesor_id',
                $profesorIds
            )
            ->get()
            ->keyBy('profesor_id');

        foreach ($horarios as $horario) {
            foreach ($horario->profesores as $horarioProfesor) {
                $profesor = $horarioProfesor->profesor;

                if (!$profesor) {
                    continue;
                }

                $datos = $datosVersion->get(
                    $horarioProfesor->profesor_id
                );

                if ($datos) {
                    $this->aplicarDatosProfesor(
                        $profesor,
                        $datos
                    );
                }
            }

            $seccion = $horario
                ->actividadSeccion
                ?->seccion;

            if (!$seccion) {
                continue;
            }

            foreach ($seccion->profesores as $seccionProfesor) {
                $profesor = $seccionProfesor->profesor;

                if (!$profesor) {
                    continue;
                }

                $datos = $datosVersion->get(
                    $seccionProfesor->profesor_id
                );

                if ($datos) {
                    $this->aplicarDatosProfesor(
                        $profesor,
                        $datos
                    );
                }
            }
        }
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
