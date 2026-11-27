<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneracionAutomatica
{
    public function __construct(private GeneracionCargaPendiente $carga) {}

    public function generar(
        Models\VersionPlanificacion $fuente,
        int $usuario,
        ?string $ip = null,
        ?string $solicitud = null
    ): Models\VersionPlanificacion {
        $solicitud ??= (string) Str::uuid();

        return DB::transaction(function () use ($fuente, $usuario, $ip, $solicitud) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($fuente->periodo_academico_id);
            $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($fuente->id);
            $anterior = DB::table('historial_acciones')
                ->where('accion', 'Generar programación automática')
                ->where('usuario_id', $usuario)
                ->where('datos->solicitud', $solicitud)
                ->first();
            if ($anterior) {
                $datos = json_decode($anterior->datos, true, 512, JSON_THROW_ON_ERROR);
                if ((int) $datos['version_base'] !== (int) $version->id) {
                    $this->rechazar('Esta solicitud ya se utilizó con otra versión base. Actualiza la página.');
                }

                return Models\VersionPlanificacion::findOrFail($anterior->registro_id);
            }

            if (! in_array($version->tipo, ['anterior', 'semestral'], true)
                || ! in_array($version->estado, ['Borrador', 'Aprobado', 'Publicado'], true)
                || ! $periodo->estado || ! $periodo->universidad->estado) {
                $this->rechazar('Selecciona una versión de un período e institución activos.');
            }

            app(ActivacionVersion::class)->bloquearDatos($periodo, $version);
            Models\Sede::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\Carrera::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\PrioridadRecurso::orderBy('id')->lockForUpdate()->get();

            $referencias = $this->prepararCargaDeRamos($periodo);
            $datos = $this->preparar($version);
            $distribuidor = app(DistribuidorCargaPendiente::class);
            $resultado = $distribuidor->distribuir(
                $datos['trabajos'], $datos['opciones'], $datos['paquetes'], $datos['fijos'], 5000000, 15
            );
            if ($datos['trabajos'] && $resultado['estado'] !== 'completo') {
                $this->rechazar(match ($resultado['estado']) {
                    'sin_opciones' => 'Hay un grupo sin horas comunes o sin espacio compatible. Revisa la disponibilidad aprobada y los requerimientos.',
                    'limite' => 'La búsqueda alcanzó su límite. Revisa los grupos con menos alternativas o genera una selección desde Completar programación.',
                    default => 'No se encontró una distribución completa compatible con las asignaciones conservadas. Revisa los horarios fijos, la disponibilidad y la capacidad de los espacios.',
                });
            }
            $plan = $resultado['plan'];
            if ($datos['trabajos'] && ! $distribuidor->valido(
                $plan, $datos['trabajos'], $datos['opciones'], $datos['paquetes'], $datos['fijos']
            )) {
                $this->rechazar('La propuesta no superó la comprobación de horarios y recursos.');
            }

            $ultima = $periodo->versionesPlanificacion()->where('tipo', 'semestral')
                ->pluck('version')->map(fn ($numero) => (float) $numero)->max() ?? 0;
            $nueva = $periodo->versionesPlanificacion()->create([
                'nombre' => 'Programación semestral',
                'tipo' => 'semestral',
                'version' => (round($ultima * 10) + 1) / 10,
                'estado' => 'Borrador',
                'es_actual' => false,
                'usuario_id' => $usuario,
                'descripcion' => 'Generación automática desde V'.$version->version
                    .'. Conserva las asignaciones de la base y completa horarios y espacios.',
            ]);

            $copias = $this->copiarBase($nueva, $datos['base']);
            foreach ($datos['trabajos'] as $trabajo) {
                $eleccion = $plan[$trabajo['id']];
                $opcion = $datos['opciones'][$eleccion['slot']];
                $paquete = $datos['paquetes'][$trabajo['grupo_id']][$eleccion['paquete']];
                $grupo = $datos['grupos']->get($trabajo['grupo_id']);
                if (isset($trabajo['horario_id'])) {
                    $horario = $copias->get($trabajo['horario_id']);
                } else {
                    $horario = $nueva->horarios()->create([
                        'actividad_seccion_id' => $grupo->actividad_seccion_id,
                        'grupo_seccion_id' => $grupo->id,
                        'dia_id' => $opcion['dia_id'],
                        'bloque_horario_id' => $opcion['bloque_id'],
                        'estado' => true,
                    ]);
                    $this->copiarDocentes($horario, $datos['plantillas']->get($grupo->id));
                }
                $this->completarEspacios($horario, $paquete);
            }
            $this->copiarHistoricos($version, $nueva);
            $avisosDisponibilidad = $this->revisarResultado($nueva, $copias);
            DB::table('historial_acciones')->insert([
                'usuario_id' => $usuario,
                'accion' => 'Generar programación automática',
                'modulo' => 'Programación semestral',
                'tabla_afectada' => 'versiones_planificacion',
                'registro_id' => $nueva->id,
                'descripcion' => 'Borrador completo con disponibilidad comprobada para los módulos nuevos.',
                'datos' => json_encode([
                    'solicitud' => $solicitud,
                    'version_base' => $version->id,
                    'carga_desde_ramos' => $referencias,
                    'conservados' => $datos['base']->count(),
                    'conservados_con_aviso_de_disponibilidad' => $avisosDisponibilidad,
                    'asignaciones_generadas' => count($datos['trabajos']),
                ], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $nueva;
        }, 3);
    }

    private function preparar(Models\VersionPlanificacion $version): array
    {
        $periodo = $version->periodoAcademico;
        $grupos = $this->carga->grupos($version)->get()->keyBy('id');
        if ($grupos->isEmpty()) {
            $this->rechazar('Prepara las secciones, actividades y grupos del período antes de generar.');
        }
        $base = $this->carga->horarios($version);
        foreach ($base as $horario) {
            if (! $horario->grupo_seccion_id) {
                $coincidentes = $grupos->where('actividad_seccion_id', $horario->actividad_seccion_id);
                if ($coincidentes->count() === 1) {
                    $horario->grupo_seccion_id = $coincidentes->first()->id;
                    $horario->setRelation('grupoSeccion', $coincidentes->first());
                }
            }
            $grupo = $grupos->get($horario->grupo_seccion_id);
            if (! $grupo || (int) $grupo->actividad_seccion_id !== (int) $horario->actividad_seccion_id) {
                $this->rechazar('El módulo #'.$horario->id.' necesita un grupo activo de su actividad.');
            }
            $horario->setRelation('grupoSeccion', $grupo);
            $horario->setRelation('actividadSeccion', $grupo->actividadSeccion);
            $horario->setRelation('docentesEfectivos', $this->carga->docentesEfectivos($horario));
        }
        $carga = (new RevisionCargaProgramacion)->revisar($version, $base);
        foreach (['excedentes', 'actividades_sin_grupos', 'grupos_sin_carga', 'modulos_sin_grupo'] as $problema) {
            if ($carga[$problema]) {
                $this->rechazar('Revisa la carga del período antes de generar: '
                    .collect($carga['observaciones'])->pluck('mensaje')->first());
            }
        }

        $dias = Models\Dia::where('estado', true)->orderBy('numero')->orderBy('id')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->orderBy('id')->get();
        $opciones = [];
        $opcionesAutomaticas = [];
        foreach ($dias as $dia) {
            foreach ($bloques as $bloque) {
                $intervalo = IntervalosHorario::deBloque($bloque);
                if (! $intervalo) continue;
                [$inicio, $fin] = $intervalo;
                $clave = $dia->id.':'.$bloque->id;
                $opciones[$clave] = [
                    'dia_id' => (int) $dia->id, 'bloque_id' => (int) $bloque->id,
                    'inicio' => $inicio, 'fin' => $fin, 'orden' => count($opciones) + 1,
                ];
                if ($this->carga->utilizable($bloque)) {
                    $opcionesAutomaticas[$clave] = $opciones[$clave];
                }
            }
        }
        if (! $opciones) $this->rechazar('El calendario no tiene módulos activos con un intervalo válido.');

        $plantillas = collect();
        $docentes = collect();
        $porGrupo = $base->groupBy('grupo_seccion_id');
        $sedesPorGrupo = $porGrupo->map(fn ($horarios) =>
            $horarios->flatMap(fn ($horario) => $this->carga->salasAsignadas($horario))->pluck('sede_id')->unique());
        foreach ($grupos as $grupo) {
            $seccion = $grupo->actividadSeccion->seccion;
            if (! $seccion->asignatura?->estado
                || (int) $seccion->asignatura->universidad_id !== (int) $periodo->universidad_id) {
                $this->rechazar('Revisa el ramo de la sección '.$seccion->codigo.'.');
            }
            $asignados = $grupo->profesores->isNotEmpty() ? $grupo->profesores : $seccion->profesores;
            $plantillas->put($grupo->id, $asignados->unique('profesor_id')->values());
            if ($porGrupo->get($grupo->id, collect())->count() < $grupo->actividadSeccion->cantidad_horas) {
                if (! $opcionesAutomaticas) $this->rechazar('El calendario no tiene módulos activos de clases fuera del almuerzo.');
                $this->agregarDocentes($docentes, $asignados, $periodo, $this->nombreGrupo($grupo));
            }
        }
        $disponibilidad = $this->carga->disponibilidadAprobada($periodo, $docentes, $dias, $bloques)['porDocente'];
        $salas = Models\Sala::where('universidad_id', $periodo->universidad_id)->orderBy('id')
            ->with('sede', 'tipoSala', 'laboratorios.sala.tipoSala')->get();
        $moviles = Models\LaboratorioMovil::where('universidad_id', $periodo->universidad_id)->orderBy('id')->get();
        $sedes = Models\Sede::where('universidad_id', $periodo->universidad_id)->where('estado', true)->orderBy('id')->get();
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)
            ->orderBy('id')->with('ubicaciones')->get()->keyBy('id');
        $reglas = Models\PrioridadRecurso::whereHas('asignatura', fn ($query) => $query->where('universidad_id', $periodo->universidad_id))
            ->orderBy('id')->with('tipoSala')->get();
        $recursos = new RevisionRecursosProgramacion($periodo->universidad_id, new RevisionEquipamientoProgramacion($inventario));
        $prioridades = new PrioridadUsoRecursos($reglas, $inventario);
        $trabajos = $paquetes = $fijos = $reservas = [];
        $incompletos = collect();
        $docentesBase = collect();
        foreach ($base as $horario) {
            $this->agregarDocentes($docentesBase, $horario->docentesEfectivos, $periodo, 'módulo #'.$horario->id);
            $clave = $horario->dia_id.':'.$horario->bloque_horario_id;
            $opcion = $opciones[$clave] ?? null;
            if (! $opcion) {
                $this->rechazar('El horario conservado #'.$horario->id
                    .' no coincide con el calendario activo de clases. Corrígelo antes de generar.');
            }
            $asignaciones = (new RevisionAsignacionesProgramacion)->revisar($horario, $horario->docentesEfectivos);
            if ($asignaciones['capacidad']) {
                $this->rechazar('Revisa la capacidad del horario conservado #'.$horario->id.': '.$asignaciones['capacidad']['mensaje']);
            }
            $grupo = $grupos->get($horario->grupo_seccion_id);
            $identificadores = $this->recursosGrupo($grupo, $horario->docentesEfectivos);
            foreach ($this->carga->salasAsignadas($horario) as $sala) $identificadores[] = 'sala:'.$sala->id;
            foreach ($horario->asignacionesLaboratorios as $lab) $identificadores[] = 'laboratorio:'.$lab->laboratorio_id;
            foreach ($horario->asignacionesLaboratoriosMoviles as $movil) $identificadores[] = 'movil:'.$movil->laboratorio_movil_id;
            $sedeIds = $sedesPorGrupo->get($grupo->id, collect());
            $reserva = $opcion + [
                'id' => $horario->id, 'grupo_id' => (int) $grupo->id,
                'sede_id' => $sedeIds->count() === 1 ? (int) $sedeIds->first() : null,
                'recursos' => array_unique($identificadores),
            ];
            $this->comprobarReserva($reserva, $reservas);
            $requiereLaboratorio = Models\RequerimientoActividad::tipoNormalizado($grupo->actividadSeccion->tipo) === 'Laboratorio'
                && $horario->asignacionesLaboratorios->isEmpty() && $horario->asignacionesLaboratoriosMoviles->isEmpty();
            if ($asignaciones['asignaciones'] || $recursos->revisar($horario) || $requiereLaboratorio) {
                $incompletos->push($horario);
            } else {
                $fijos[] = $reserva;
            }
        }
        foreach ($grupos as $grupo) {
            $existentes = $porGrupo->get($grupo->id, collect());
            $faltan = (int) $grupo->actividadSeccion->cantidad_horas - $existentes->count();
            $aCompletar = $incompletos->where('grupo_seccion_id', $grupo->id);
            if (! $faltan && $aCompletar->isEmpty()) continue;
            $permitidas = $this->carga->sedesPermitidas($grupo, $sedes);
            $sedesFijas = $sedesPorGrupo->get($grupo->id, collect());
            if ($sedesFijas->isNotEmpty()) $permitidas = $permitidas->whereIn('id', $sedesFijas);
            $asignados = $plantillas->get($grupo->id);
            $docentesEspacio = $asignados->isNotEmpty() ? $asignados : $existentes->first()->docentesEfectivos;
            $paquetes[$grupo->id] = [];
            foreach ($permitidas as $sede) {
                $intentos = 0;
                $paquetes[$grupo->id] += app(EspaciosCargaPendiente::class)->candidatos(
                    $grupo, $sede->id, $docentesEspacio, $salas, $moviles, $recursos, $prioridades, $inventario, $intentos
                );
            }
            if (! $paquetes[$grupo->id]) {
                $this->rechazar($this->nombreGrupo($grupo)
                    .' no tiene sala o laboratorio compatible con su capacidad y requerimientos en las sedes de sus carreras.');
            }
            // Una base manual puede distribuir el grupo entre sedes expresamente.
            if ($sedesFijas->count() > 1) {
                foreach ($paquetes[$grupo->id] as &$paquete) unset($paquete['sede_id']);
                unset($paquete);
            }
            foreach ($aCompletar as $horario) {
                $restricciones = $this->carga->salasAsignadas($horario)->map(fn ($sala) => 'sala:'.$sala->id)
                    ->concat($horario->asignacionesLaboratorios->map(fn ($lab) => 'laboratorio:'.$lab->laboratorio_id))
                    ->concat($horario->asignacionesLaboratoriosMoviles->map(fn ($movil) => 'movil:'.$movil->laboratorio_movil_id))->all();
                $admitidos = array_keys(array_filter($paquetes[$grupo->id], fn ($paquete) => ! array_diff($restricciones, $paquete['recursos'])));
                if (! $admitidos) {
                    $this->rechazar('No se puede completar el horario #'.$horario->id
                        .' conservando sus espacios. Revisa el aforo y los requerimientos de '.$this->nombreGrupo($grupo).'.');
                }
                $trabajos[] = [
                    'id' => 'existente:'.$horario->id, 'horario_id' => $horario->id,
                    'orden' => 0, 'grupo_id' => (int) $grupo->id,
                    'recursos' => $this->recursosGrupo($grupo, $horario->docentesEfectivos),
                    'opciones' => [$horario->dia_id.':'.$horario->bloque_horario_id],
                    'paquetes' => $admitidos, 'carga' => (int) $grupo->actividadSeccion->cantidad_horas,
                    'prioridad' => min(array_column(array_intersect_key($paquetes[$grupo->id], array_flip($admitidos)), 'prioridad')),
                ];
            }
            $admitidas = array_keys(array_filter(
                $opcionesAutomaticas, fn ($opcion) => $this->disponibles($asignados, $opcion, $disponibilidad)
            ));
            if ($faltan && ! $admitidas) {
                $this->rechazar($this->nombreGrupo($grupo).' no tiene horas comunes en la disponibilidad aprobada de sus docentes.');
            }
            for ($numero = 1; $numero <= $faltan; $numero++) {
                $trabajos[] = [
                    'id' => 'nuevo:'.$grupo->id.':'.$numero, 'orden' => $numero, 'grupo_id' => (int) $grupo->id,
                    'recursos' => $this->recursosGrupo($grupo, $asignados), 'opciones' => $admitidas,
                    'carga' => (int) $grupo->actividadSeccion->cantidad_horas,
                    'prioridad' => min(array_column($paquetes[$grupo->id], 'prioridad')),
                ];
            }
        }

        return compact('grupos', 'base', 'plantillas', 'trabajos', 'opciones', 'paquetes', 'fijos');
    }

    private function prepararCargaDeRamos(Models\PeriodoAcademico $periodo): array
    {
        $secciones = Models\Seccion::where('periodo_academico_id', $periodo->id)->where('estado_activo', true)
            ->with(['asignatura', 'actividades' => fn ($query) => $query->with('grupos')->withCount('horarios')])
            ->orderBy('id')->get();
        $cerrado = $periodo->versionesPlanificacion()->where('tipo', 'semestral')
            ->whereIn('estado', ['Aprobado', 'Publicado'])->exists();
        $normalizar = static fn (string $tipo) => Models\RequerimientoActividad::tipoNormalizado($tipo)
            ?? Str::lower(Str::ascii(Str::squish($tipo)));
        $cambios = [];
        foreach ($secciones as $seccion) {
            $ramo = $seccion->asignatura;
            if (! $ramo?->estado) continue;
            foreach (GestionOfertaPeriodo::ACTIVIDADES as $tipo => $campo) {
                $modulos = (int) $ramo->$campo;
                if ($modulos <= 0) continue;
                $coincidentes = $seccion->actividades->filter(fn ($actividad) => $normalizar($actividad->tipo) === $normalizar($tipo));
                if ($coincidentes->count() > 1) continue;
                $actividad = $coincidentes->first();
                if ($actividad && ! $actividad->estado) {
                    $this->rechazar($ramo->codigo.' · Sección '.$seccion->codigo
                        .': la actividad '.$tipo.' está inactiva. Revisa su estado antes de utilizar la carga del ramo.');
                }
                $crearActividad = ! $actividad;
                $definirCarga = $actividad && (int) $actividad->cantidad_horas <= 0 && (int) $actividad->horarios_count === 0;
                $crearGrupo = ! $actividad || $actividad->grupos->isEmpty();
                if ($cerrado && ($crearActividad || $definirCarga || $crearGrupo)) {
                    $this->rechazar('La carga de '.$ramo->codigo.' requiere completar '.$tipo
                        .', pero el período tiene una programación aprobada o publicada. Conserva su definición académica.');
                }
                if ($crearActividad) {
                    $actividad = $seccion->actividades()->create([
                        'tipo' => $tipo,
                        'cantidad_horas' => $modulos,
                        'cantidad_estudiantes' => $seccion->inscritos ?: $seccion->cupos,
                        'modalidad' => $seccion->modalidad,
                        'estado' => true,
                    ]);
                } elseif ($definirCarga) {
                    $actividad->update(['cantidad_horas' => $modulos]);
                }
                if ($crearGrupo) {
                    $actividad->grupos()->create([
                        'codigo' => '1', 'numero' => 1, 'cupos' => $seccion->cupos,
                        'inscritos' => $seccion->inscritos, 'estado' => 'Activo', 'estado_activo' => true,
                    ]);
                } elseif (! $actividad->grupos->contains('estado_activo', true)) {
                    $this->rechazar($ramo->codigo.' · Sección '.$seccion->codigo
                        .': activa un grupo de '.$tipo.' antes de generar.');
                }
                if ($crearActividad || $definirCarga || $crearGrupo) {
                    $cambios[] = [
                        'actividad_id' => $actividad->id,
                        'modulos_referencia' => $modulos,
                        'actividad_creada' => $crearActividad,
                        'carga_definida' => (bool) $definirCarga,
                        'grupo_creado' => $crearGrupo,
                    ];
                }
            }
        }

        return $cambios;
    }

    private function agregarDocentes(Collection $todos, Collection $asignados, Models\PeriodoAcademico $periodo, string $nombre): void
    {
        if ($asignados->isEmpty()) $this->rechazar('Asigna docentes a '.$nombre.' antes de generar.');
        foreach ($asignados as $asignacion) {
            $profesor = $asignacion->profesor;
            if (! $profesor?->estado || (int) $profesor->universidad_id !== (int) $periodo->universidad_id) {
                $this->rechazar('Revisa los docentes de '.$nombre.': deben estar activos y pertenecer a la institución.');
            }
            $todos->put($profesor->id, $profesor);
        }
    }

    private function disponibles(Collection $docentes, array $opcion, Collection $disponibilidad): bool
    {
        return $docentes->isNotEmpty() && $docentes->every(fn ($docente) =>
            IntervalosHorario::coberturaDisponibilidad(
                [$opcion['inicio'], $opcion['fin']],
                $disponibilidad->get($docente->profesor_id, collect())->where('dia_id', $opcion['dia_id'])
            ) === 'cubierto');
    }

    private function recursosGrupo(Models\GrupoSeccion $grupo, Collection $docentes): array
    {
        return array_values(array_unique([
            'curso:'.$grupo->actividadSeccion->seccion_id, 'grupo:'.$grupo->id,
            ...$docentes->pluck('profesor_id')->map(fn ($id) => 'profesor:'.$id)->all(),
        ]));
    }

    private function comprobarReserva(array $reserva, array &$ocupados): void
    {
        foreach ($reserva['recursos'] as $recurso) {
            foreach ($ocupados[$reserva['dia_id']][$recurso] ?? [] as $anterior) {
                if (IntervalosHorario::seSuperponen(
                    [$reserva['inicio'], $reserva['fin']], [$anterior['inicio'], $anterior['fin']]
                )) {
                    $this->rechazar('Los horarios conservados #'.$anterior['id'].' y #'.$reserva['id']
                        .' tienen un tope. Resuélvelo antes de generar.');
                }
            }
            $ocupados[$reserva['dia_id']][$recurso][] = $reserva;
        }
    }

    private function copiarBase(Models\VersionPlanificacion $nueva, Collection $base): Collection
    {
        $copias = collect();
        foreach ($base as $horario) {
            $copia = $horario->replicate();
            $copia->version_planificacion_id = $nueva->id;
            $copia->setRelations([]);
            $copia->save();
            $this->copiarDocentes($copia, $horario->docentesEfectivos);
            foreach (['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'] as $relacion) {
                foreach ($horario->$relacion as $asignacion) {
                    $registro = $asignacion->replicate();
                    $registro->horario_id = $copia->id;
                    $registro->setRelations([]);
                    $registro->save();
                }
            }
            $copias->put($horario->id, $copia);
        }

        return $copias;
    }

    private function copiarHistoricos(Models\VersionPlanificacion $fuente, Models\VersionPlanificacion $nueva): void
    {
        $historicos = Models\ProfesorVersion::where('version_planificacion_id', $fuente->id)->get()->keyBy('profesor_id');
        $ids = Models\HorarioProfesor::whereIn('horario_id', $nueva->horarios()->select('id'))->pluck('profesor_id')
            ->merge($historicos->keys())->unique();
        foreach (Models\Profesor::whereIn('id', $ids)->get() as $profesor) {
            $original = $historicos->get($profesor->id) ?? $profesor;
            $nuevaId = ['version_planificacion_id' => $nueva->id, 'profesor_id' => $profesor->id];
            Models\ProfesorVersion::create($nuevaId + $original->only([
                'rut', 'nombre', 'apellido', 'correo_institucional', 'correo_personal',
                'tipo_contrato', 'tipo_profesor', 'unidad_academica',
            ]));
        }
    }

    private function copiarDocentes(Models\Horario $horario, Collection $asignados): void
    {
        foreach ($asignados as $asignacion) {
            $horario->profesores()->create($asignacion->only([
                'profesor_id', 'tipo_participacion', 'es_principal', 'observaciones',
            ]) + ['estado' => true]);
        }
    }

    private function completarEspacios(Models\Horario $horario, array $paquete): void
    {
        $valores = ['capacidad_asignada' => $paquete['cantidad'], 'es_principal' => true, 'estado' => true];
        $horario->asignacionesSalas()->firstOrCreate(['sala_id' => $paquete['sala_id'], 'estado' => true], $valores);
        if ($paquete['laboratorio_id']) {
            $horario->asignacionesLaboratorios()->firstOrCreate(['laboratorio_id' => $paquete['laboratorio_id'], 'estado' => true], $valores);
        }
        if ($paquete['movil_id']) {
            $horario->asignacionesLaboratoriosMoviles()->firstOrCreate(['laboratorio_movil_id' => $paquete['movil_id'], 'estado' => true], $valores);
        }
    }

    private function revisarResultado(Models\VersionPlanificacion $version, Collection $copias): int
    {
        $revision = app(RevisionConflictosProgramacion::class)->revisar($version);
        $idsConservados = $copias->pluck('id')->flip();
        $bloqueos = $revision['observaciones']->reject(fn (array $fila) => $fila['tipo'] === 'disponibilidad'
            && $idsConservados->has($fila['horarios'][0]['id']));
        if ($bloqueos->isNotEmpty()) {
            $this->rechazar('La programación no superó su revisión final. '.$bloqueos->first()['mensaje']);
        }

        return $revision['disponibilidad'];
    }

    private function nombreGrupo(Models\GrupoSeccion $grupo): string
    {
        return 'ramo '.$grupo->actividadSeccion->seccion->asignatura->codigo
            .', sección '.$grupo->actividadSeccion->seccion->codigo.', grupo '.$grupo->codigo;
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['generacion' => $mensaje]);
    }
}
