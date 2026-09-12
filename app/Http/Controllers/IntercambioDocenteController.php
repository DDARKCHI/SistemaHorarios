<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\VersionPlanificacion;
use Illuminate\Http\Request;

class IntercambioDocenteController extends Controller
{
    public function index(Request $request)
    {
        /*
         * Obtenemos únicamente la versión de planificación
         * marcada actualmente como vigente.
         */
        $versionActual = VersionPlanificacion::where('es_actual', true)
            ->latest('id')
            ->first();

        /*
         * Listado de asignaturas disponibles para realizar
         * la búsqueda de intercambio docente.
         */
        $asignaturas = Asignatura::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'codigo',
                'nombre',
            ]);

        $asignatura = null;
        $resultados = collect();

        /*
         * Si el usuario seleccionó una asignatura,
         * buscamos sus profesores.
         */
        if ($request->filled('asignatura_id')) {

            $asignatura = Asignatura::with([
                'secciones' => function ($query) use ($versionActual) {

                    $query->with([
                        'profesores.profesor',

                        'actividades.grupos.horarios' => function ($query) use ($versionActual) {

                            $query
                                ->where(
                                    'version_planificacion_id',
                                    $versionActual?->id
                                )
                                ->with([
                                    'dia',
                                    'bloqueHorario',
                                    'profesores',
                                    'asignacionesSalas.sala.sede',
                                ]);
                        },
                    ]);
                },
            ])->find($request->asignatura_id);

            if ($asignatura) {

                /*
                 * Obtenemos los profesores que participan
                 * en la asignatura seleccionada.
                 */
                $resultados = $asignatura->secciones
                    ->flatMap(function ($seccion) use ($versionActual) {

                        return $seccion->profesores->map(
                            function ($seccionProfesor) use ($seccion, $versionActual) {

                                $profesor = $seccionProfesor->profesor;

                                if (!$profesor) {
                                    return null;
                                }

                                /*
                                 * -------------------------------------------------
                                 * 1. HORARIOS DEL PROFESOR EN LA ASIGNATURA BUSCADA
                                 * -------------------------------------------------
                                 */
                                $horariosAsignatura = $seccion->actividades
                                    ->flatMap(function ($actividad) {

                                        return $actividad->grupos
                                            ->flatMap(function ($grupo) {

                                                return $grupo->horarios->map(
                                                    function ($horario) use ($grupo) {

                                                        return [
                                                            'horario' => $horario,
                                                            'grupo' => $grupo,
                                                        ];
                                                    }
                                                );
                                            });
                                    })
                                    ->filter(function ($item) use ($profesor) {

                                        return $item['horario']->profesores
                                            ->contains(
                                                'profesor_id',
                                                $profesor->id
                                            );
                                    })
                                    ->unique(function ($item) {

                                        return $item['horario']->id;
                                    })
                                    ->values();

                                /*
                                 * Sedes donde realmente imparte la
                                 * asignatura seleccionada.
                                 */
                                $sedesAsignatura = $horariosAsignatura
                                    ->flatMap(function ($item) {

                                        return $item['horario']
                                            ->asignacionesSalas
                                            ->map(function ($asignacion) {

                                                return $asignacion
                                                    ->sala?->sede;
                                            });
                                    })
                                    ->filter()
                                    ->unique('id')
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * 2. CARGA COMPLETA DEL PROFESOR
                                 * -------------------------------------------------
                                 *
                                 * Buscamos directamente todos los horarios
                                 * asociados al profesor en la versión actual.
                                 */
                                $horariosCarga = $profesor->horarios()
                                    ->whereHas('horario', function ($query) use ($versionActual) {

                                        $query->where(
                                            'version_planificacion_id',
                                            $versionActual?->id
                                        );
                                    })
                                    ->with([
                                        'horario.dia',
                                        'horario.bloqueHorario',
                                        'horario.actividadSeccion.seccion.asignatura',
                                        'horario.asignacionesSalas.sala.sede',
                                    ])
                                    ->get()
                                    ->map(function ($horarioProfesor) {

                                        return $horarioProfesor->horario;
                                    })
                                    ->filter()
                                    ->unique('id')
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * 3. SEDES DE TODA LA CARGA DEL PROFESOR
                                 * -------------------------------------------------
                                 */
                                $sedesCarga = $horariosCarga
                                    ->flatMap(function ($horario) {

                                        return $horario
                                            ->asignacionesSalas
                                            ->map(function ($asignacion) {

                                                return $asignacion
                                                    ->sala?->sede;
                                            });
                                    })
                                    ->filter()
                                    ->unique('id')
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * 4. ASIGNATURAS QUE ACTUALMENTE DICTA
                                 * -------------------------------------------------
                                 */
                                $asignaturasCarga = $horariosCarga
                                    ->map(function ($horario) {

                                        return $horario
                                            ->actividadSeccion
                                            ?->seccion
                                            ?->asignatura;
                                    })
                                    ->filter()
                                    ->unique('id')
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * 5. HORARIOS OCUPADOS
                                 * -------------------------------------------------
                                 */
                                $horariosOcupados = $horariosCarga
                                    ->map(function ($horario) {

                                        return [
                                            'horario_id' => $horario->id,

                                            'dia_id' => $horario->dia_id,

                                            'dia' => $horario->dia?->nombre,

                                            'bloque_id' => $horario->bloque_horario_id,

                                            'bloque' =>
                                                $horario->bloqueHorario?->numero,

                                            'hora_inicio' =>
                                                $horario->bloqueHorario?->hora_inicio,

                                            'hora_fin' =>
                                                $horario->bloqueHorario?->hora_fin,

                                            'asignatura' =>
                                                $horario
                                                    ->actividadSeccion
                                                    ?->seccion
                                                    ?->asignatura,
                                        ];
                                    })
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * 6. AGRUPAR CARGA POR ASIGNATURA
                                 * -------------------------------------------------
                                 */
                                $cargaPorAsignatura = $horariosCarga
                                    ->groupBy(function ($horario) {

                                        return $horario
                                            ->actividadSeccion
                                            ?->seccion
                                            ?->asignatura
                                            ?->id;
                                    })
                                    ->map(function ($horarios) {

                                        $asignaturaCarga =
                                            $horarios
                                                ->first()
                                                ?->actividadSeccion
                                                ?->seccion
                                                ?->asignatura;

                                        return [
                                            'asignatura' => $asignaturaCarga,

                                            'cantidad_horarios' =>
                                                $horarios->count(),

                                            'horarios' => $horarios->values(),
                                        ];
                                    })
                                    ->filter(function ($item) {

                                        return $item['asignatura'] !== null;
                                    })
                                    ->values();

                                /*
                                 * -------------------------------------------------
                                 * RESULTADO DEL PROFESOR
                                 * -------------------------------------------------
                                 */
                                return [

                                    'profesor' => $profesor,

                                    /*
                                     * Sedes donde imparte la asignatura
                                     * que estamos buscando.
                                     */
                                    'sedes' => $sedesAsignatura,

                                    /*
                                     * Sedes donde actualmente tiene
                                     * cualquier actividad docente.
                                     */
                                    'sedes_carga' => $sedesCarga,

                                    /*
                                     * Horarios correspondientes a la
                                     * asignatura seleccionada.
                                     */
                                    'horarios' => $horariosAsignatura,

                                    /*
                                     * Toda la carga horaria actual.
                                     */
                                    'horarios_carga' => $horariosCarga,

                                    /*
                                     * Horarios preparados para análisis
                                     * de conflictos.
                                     */
                                    'horarios_ocupados' => $horariosOcupados,

                                    /*
                                     * Carga agrupada por asignatura.
                                     */
                                    'carga_por_asignatura' =>
                                        $cargaPorAsignatura,

                                    /*
                                     * Cantidad total de bloques actualmente
                                     * ocupados.
                                     */
                                    'cantidad_horarios' =>
                                        $horariosCarga->count(),

                                    /*
                                     * Cantidad de asignaturas distintas
                                     * que actualmente imparte.
                                     */
                                    'cantidad_asignaturas' =>
                                        $asignaturasCarga->count(),

                                    /*
                                     * Asignaturas actuales del profesor.
                                     */
                                    'asignaturas_carga' =>
                                        $asignaturasCarga,

                                    /*
                                     * Participación en la asignatura buscada.
                                     */
                                    'tipo_participacion' =>
                                        $seccionProfesor->tipo_participacion,

                                    'es_principal' =>
                                        $seccionProfesor->es_principal,
                                ];
                            }
                        );
                    })
                    ->filter()
                    ->unique(function ($resultado) {

                        /*
                         * Evitamos repetir al mismo profesor si participa
                         * en más de una sección de la asignatura buscada.
                         */
                        return $resultado['profesor']->id;
                    })
                    ->values();
            }
        }

        return view('programacion.intercambio', [
            'asignaturas' => $asignaturas,
            'asignatura' => $asignatura,
            'resultados' => $resultados,
            'versionActual' => $versionActual,
        ]);
    }
}