<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DatosPanelCarrera
{
    public function __construct(private RevisionConflictosProgramacion $revision) {}

    public function consultar(Models\VersionPlanificacion $version, Models\Carrera $carrera): array
    {
        $periodo = $version->periodoAcademico;
        if ((int) $periodo->universidad_id !== (int) $carrera->universidad_id) {
            throw new InvalidArgumentException('La carrera y el período deben pertenecer a la misma institución.');
        }

        $activos = fn ($consulta) => $consulta->where('estado', true);
        $oferta = $carrera->secciones()->where('periodo_academico_id', $periodo->id)->where('estado_activo', true)
            ->with([
                'asignatura', 'profesores' => $activos, 'profesores.profesor',
                'actividades' => $activos, 'actividades.grupos' => fn ($consulta) => $consulta->where('estado_activo', true)->orderBy('id'),
                'actividades.grupos.profesores' => $activos, 'actividades.grupos.profesores.profesor',
            ])->get()->sortBy(fn ($seccion) => [(int) $seccion->semestre, $seccion->asignatura?->codigo, $seccion->codigo])->values();
        $seccionesIds = $oferta->modelKeys();

        $horariosVersion = $this->revision->cargarHorarios($version, ['actividadSeccion.seccion.carreras']);

        $horariosCarrera = $horariosVersion->whereIn('actividadSeccion.seccion_id', $seccionesIds)->values();
        $horariosIds = array_fill_keys($horariosCarrera->modelKeys(), true);
        $dias = Models\Dia::where(fn ($query) => $query->where('estado', true)->orWhereIn('id', $horariosCarrera->pluck('dia_id')->unique()))
            ->orderBy('numero')->orderBy('id')->get();
        $bloques = Models\BloqueHorario::where(fn ($query) => $query->where('estado', true)->orWhereIn('id', $horariosCarrera->pluck('bloque_horario_id')->unique()))
            ->orderBy('hora_inicio')->orderBy('id')->get();
        $calendario = $this->calendario($dias, $bloques);

        $nombresVersion = $version->tipo === 'semestral' && $version->es_actual && $version->estado === 'Borrador'
            ? new \Illuminate\Database\Eloquent\Collection
            : Models\ProfesorVersion::where('version_planificacion_id', $version->id)->get()->keyBy('profesor_id');
        $responsables = $horariosVersion->mapWithKeys(fn (Models\Horario $horario) => [$horario->id => $this->docentesEfectivos($horario)]);
        $docentesOferta = $oferta->flatMap(fn (Models\Seccion $seccion) => $seccion->profesores->concat(
            $seccion->actividades->flatMap(fn ($actividad) => $actividad->grupos->flatMap->profesores)
        ));
        $docentesCarrera = $docentesOferta->concat($responsables->only(array_keys($horariosIds))->flatten(1))->unique('profesor_id');
        $idsDocentes = $docentesCarrera->pluck('profesor_id')->all();
        $aprobados = Models\RespuestaDisponibilidad::whereIn('profesor_id', $idsDocentes)->where('respuesta', 'Aprobada')
            ->whereHas('solicitudDisponibilidad', fn ($query) => $query->where('periodo_academico_id', $periodo->id)->where('estado', 'Aprobada'))
            ->distinct()->pluck('profesor_id')->flip();
        $declaradas = Models\DisponibilidadProfesor::whereIn('profesor_id', $idsDocentes)->where('periodo_academico_id', $periodo->id)->where('estado', true)
            ->with('bloqueHorario')->get()->groupBy('profesor_id');

        $secciones = $this->secciones($oferta, $horariosCarrera, $responsables, $nombresVersion, $aprobados, (int) $periodo->universidad_id);
        $observaciones = $this->observaciones($version, $horariosCarrera, $horariosVersion, $seccionesIds, $nombresVersion);
        foreach ($secciones as $seccion) {
            foreach ($seccion['carga_sin_actividad'] as $pendiente) {
                $observaciones[] = ['tipo' => 'carga', 'nivel' => 'ambar', 'dia' => null, 'intervalo' => null, 'horarios' => [],
                    'titulo' => 'Actividad pendiente · '.$seccion['ramo_codigo'].' · Sección '.$seccion['codigo'],
                    'mensaje' => 'El ramo indica '.$pendiente['requeridos'].' módulos de '.mb_strtolower($pendiente['actividad']).'. Define una actividad de ese tipo y su grupo en la oferta de esta sección.'];
            }
        }
        $alertas = $this->alertas($observaciones);
        $horarios = $horariosCarrera->map(fn (Models\Horario $horario) => $this->horario(
            $horario, $responsables->get($horario->id), $nombresVersion, $alertas[$horario->id] ?? []
        ))->all();
        $indicadores = $this->indicadores($secciones, $horarios, $observaciones);

        return [
            'dias' => $dias, 'bloques' => $bloques, 'secciones' => $secciones, 'horarios' => $horarios,
            'indicadores' => $indicadores, 'observaciones' => $observaciones,
            'semestres' => $oferta->pluck('semestre')->map(fn ($valor) => (int) $valor)->unique()->sort()->values()->all(),
            'docentes' => $this->cargaDocente($docentesCarrera, $horariosVersion, $responsables, $calendario, $declaradas, $aprobados, $horariosIds, $nombresVersion),
            'laboratorios' => $this->ocupacionLaboratorios($carrera, $horariosVersion, $horariosCarrera, $calendario, $horariosIds),
        ];
    }

    private function calendario(Collection $dias, Collection $bloques): array
    {
        $calendario = [];
        foreach ($dias->where('estado', true) as $dia) {
            foreach ($bloques->where('estado', true) as $bloque) {
                $intervalo = IntervalosHorario::deBloque($bloque);
                if ($intervalo) {
                    $calendario[$dia->id.':'.$bloque->id] = ['dia' => (int) $dia->id, 'intervalo' => $intervalo,
                        'almuerzo' => $bloque->es_almuerzo || IntervalosHorario::seSuperponen($intervalo, [13 * 3600, 14 * 3600])];
                }
            }
        }

        return $calendario;
    }

    private function docentesEfectivos(Models\Horario $horario): Collection
    {
        if ($horario->profesores->isNotEmpty()) return $horario->profesores;
        if ($horario->grupoSeccion?->profesores->isNotEmpty()) return $horario->grupoSeccion->profesores;

        return $horario->actividadSeccion?->seccion?->profesores ?? collect();
    }

    private function docentesResumen(Collection $docentes, Collection $nombresVersion): array
    {
        return $docentes->sortByDesc('es_principal')->unique('profesor_id')->values()->map(function ($fila) use ($nombresVersion) {
            $docente = $nombresVersion->get($fila->profesor_id) ?? $fila->profesor;
            $nombre = trim(($docente?->nombre ?? '').' '.($docente?->apellido ?? ''));

            return ['id' => (int) $fila->profesor_id, 'nombre' => $nombre ?: 'Docente #'.$fila->profesor_id,
                'es_principal' => (bool) $fila->es_principal, 'activo' => (bool) $fila->profesor?->estado];
        })->all();
    }

    private function secciones(Collection $oferta, Collection $horarios, Collection $responsables, Collection $nombresVersion, Collection $aprobados, int $universidadId): array
    {
        $porGrupo = $horarios->groupBy('grupo_seccion_id');

        return $oferta->map(function (Models\Seccion $seccion) use ($porGrupo, $responsables, $nombresVersion, $aprobados, $universidadId) {
            $grupos = [];
            $docentesSeccion = collect();
            $cargaSinActividad = $this->cargaSinActividad($seccion);
            $cargaSinGrupos = [];
            $completa = $seccion->actividades->isNotEmpty() && ! $cargaSinActividad;
            $conDocente = $completa;
            foreach ($seccion->actividades as $actividad) {
                if ($actividad->grupos->isEmpty()) {
                    $completa = false;
                    $conDocente = false;
                    if ((int) $actividad->cantidad_horas > 0) {
                        $cargaSinGrupos[] = $this->cargaPendiente($this->actividad($actividad->tipo), (int) $actividad->cantidad_horas);
                    }
                }
                foreach ($actividad->grupos as $grupo) {
                    $programados = $porGrupo->get($grupo->id, collect())->where('actividad_seccion_id', $actividad->id);
                    $requeridos = max(0, (int) $actividad->cantidad_horas);
                    $asignados = $programados->count();
                    $plantilla = $grupo->profesores->isNotEmpty() ? $grupo->profesores : $seccion->profesores;
                    $docentes = $programados->flatMap(fn ($horario) => $responsables->get($horario->id));
                    if ($asignados === 0 || $asignados < $requeridos) $docentes = $docentes->concat($plantilla);
                    $docentesSeccion = $docentesSeccion->concat($docentes);
                    // Un módulo sin docente o una parte pendiente sin plantilla impiden dar la docencia por cubierta.
                    $conDocente = $conDocente && $docentes->isNotEmpty()
                        && $docentes->every(fn ($fila) => $fila->profesor?->estado && (int) $fila->profesor->universidad_id === $universidadId)
                        && ($asignados >= $requeridos || $plantilla->isNotEmpty())
                        && $programados->every(fn ($horario) => $responsables->get($horario->id)->isNotEmpty());
                    $completa = $completa && $requeridos > 0 && $requeridos === $asignados;
                    $grupos[] = [
                        'id' => (int) $grupo->id, 'codigo' => $grupo->codigo, 'actividad' => $this->actividad($actividad->tipo),
                        'actividad_id' => (int) $actividad->id, 'requeridos' => $requeridos, 'asignados' => $asignados,
                        'pendientes' => max(0, $requeridos - $asignados), 'excedentes' => $requeridos > 0 ? max(0, $asignados - $requeridos) : 0,
                        'docentes' => $this->docentesResumen($docentes, $nombresVersion), 'plantilla' => $this->docentesResumen($plantilla, $nombresVersion),
                    ];
                }
            }

            $propios = $docentesSeccion->unique('profesor_id');
            return [
                'id' => (int) $seccion->id, 'codigo' => $seccion->codigo, 'ramo_codigo' => $seccion->asignatura?->codigo,
                'ramo' => $seccion->asignatura?->nombre ?? 'Ramo sin nombre', 'semestre' => (int) $seccion->semestre,
                'cupos' => $seccion->cupos, 'inscritos' => (int) $seccion->inscritos,
                'docentes' => $this->docentesResumen($propios, $nombresVersion), 'grupos' => $grupos,
                'carga_sin_actividad' => $cargaSinActividad, 'carga_sin_grupos' => $cargaSinGrupos,
                'con_docente' => $conDocente, 'carga_completa' => $completa,
                'disponibilidad_aprobada' => $conDocente && $propios->every(fn ($fila) => $aprobados->has($fila->profesor_id)),
            ];
        })->all();
    }

    private function cargaSinActividad(Models\Seccion $seccion): array
    {
        $carga = [];
        $tiposExistentes = $seccion->actividades->map(fn ($actividad) => $this->tipoComparable($this->actividad($actividad->tipo)));
        foreach (GestionOfertaPeriodo::ACTIVIDADES as $tipo => $campo) {
            $modulos = (int) $seccion->asignatura?->$campo;
            if ($modulos > 0 && ! $tiposExistentes->contains($this->tipoComparable($tipo))) {
                $carga[] = $this->cargaPendiente($tipo, $modulos);
            }
        }

        return $carga;
    }

    private function cargaPendiente(string $tipo, int $modulos): array
    {
        return ['actividad' => $tipo, 'requeridos' => $modulos, 'asignados' => 0, 'pendientes' => $modulos, 'excedentes' => 0];
    }

    private function tipoComparable(?string $tipo): string
    {
        return Str::lower(Str::ascii(Str::squish($tipo ?? '')));
    }

    private function actividad(?string $tipo): string
    {
        return match ($this->tipoComparable($tipo)) {
            'cat', 'catedra' => 'Cátedra',
            'lab', 'laboratorio', 'laboratorios', 'laboratorio fijo' => 'Laboratorio',
            default => $tipo ?: 'Actividad',
        };
    }

    private function horario(Models\Horario $horario, Collection $docentes, Collection $nombresVersion, array $alerta): array
    {
        $seccion = $horario->actividadSeccion->seccion;
        $salasDeLaboratorio = $horario->asignacionesLaboratorios->pluck('laboratorio.sala_id')->filter();
        $espacios = $horario->asignacionesSalas->reject(fn ($asignacion) => $salasDeLaboratorio->contains($asignacion->sala_id))
            ->map(fn ($asignacion) => $this->espacio('sala', $asignacion->sala_id, $asignacion->sala))
            ->concat($horario->asignacionesLaboratorios->map(fn ($asignacion) => $this->espacio('fijo', $asignacion->laboratorio_id, $asignacion->laboratorio)))
            ->concat($horario->asignacionesLaboratoriosMoviles->map(fn ($asignacion) => $this->espacio('movil', $asignacion->laboratorio_movil_id, $asignacion->laboratorioMovil)
                + ['motivo' => $asignacion->observaciones]))
            ->unique(fn ($espacio) => $espacio['tipo'].':'.$espacio['id'])->values()->all();

        return [
            'id' => (int) $horario->id, 'grupo_id' => $horario->grupo_seccion_id ? (int) $horario->grupo_seccion_id : null,
            'seccion_id' => (int) $seccion->id, 'ramo_codigo' => $seccion->asignatura?->codigo,
            'ramo' => $seccion->asignatura?->nombre ?? 'Ramo sin nombre', 'semestre' => (int) $seccion->semestre,
            'seccion' => $seccion->codigo, 'grupo' => $horario->grupoSeccion?->codigo, 'actividad' => $this->actividad($horario->actividadSeccion->tipo),
            'dia_id' => (int) $horario->dia_id, 'bloque_id' => (int) $horario->bloque_horario_id,
            'docentes' => $this->docentesResumen($docentes, $nombresVersion), 'espacios' => $espacios,
            'nivel' => $alerta['nivel'] ?? 'verde', 'motivos' => $alerta['motivos'] ?? [],
        ];
    }

    private function espacio(string $tipo, int $id, ?Model $sala): array
    {
        return ['tipo' => $tipo, 'id' => $id, 'nombre' => $sala?->codigo ?: ($sala?->nombre ?: 'Espacio #'.$id)];
    }

    private function observaciones(Models\VersionPlanificacion $version, Collection $horarios, Collection $horariosVersion, array $seccionesIds, Collection $nombresVersion): array
    {
        if (! $seccionesIds) return [];

        $observaciones = $this->revision->revisar($version, $horariosVersion, $seccionesIds, $nombresVersion)['observaciones'];
        $idsHorarios = array_fill_keys($horarios->modelKeys(), true);
        $idsSecciones = array_fill_keys($seccionesIds, true);
        $porId = $horariosVersion->keyBy('id');
        $pares = [];
        $filas = [];
        foreach ($observaciones as $fila) {
            $ids = array_column($fila['horarios'], 'id');
            $propios = array_values(array_filter($ids, fn ($id) => isset($idsHorarios[$id])));
            $cargaPropia = collect($fila['recursos'])->contains(fn ($recurso) => $recurso['tipo'] === 'curso' && isset($idsSecciones[$recurso['id']]));
            if (! $propios && ! $cargaPropia) continue;
            $nivel = $fila['tipo'] === 'datos' ? 'rojo' : 'ambar';
            $mensaje = $fila['mensaje'];
            if ($fila['tipo'] === 'conflicto' && count($ids) === 2) {
                [$a, $b] = $ids;
                $primero = $porId->get($a);
                $segundo = $porId->get($b);
                $comparten = MovimientosPanelCarrera::comparteEstudiantes($primero, $segundo);
                $recursosReales = collect($fila['recursos'])->reject(fn ($r) => $r['tipo'] === 'curso' && ! $comparten);
                if ($recursosReales->isEmpty() && ! $comparten) continue;
                if ($comparten || $recursosReales->contains(fn ($recurso) => $recurso['tipo'] !== 'sala')
                    || $this->esLaboratorio($primero) || $this->esLaboratorio($segundo)) $nivel = 'rojo';
                $pares[$this->clavePar($a, $b)] = true;
                if ($comparten) $mensaje = 'Dos clases de la misma cohorte coinciden en este intervalo. '.$mensaje;
            }
            $nombre = $fila['clase']['asignatura'] ?? null;
            $filas[] = [
                'tipo' => $fila['tipo'], 'mensaje' => $mensaje, 'horarios' => $propios, 'nivel' => $nivel,
                'titulo' => ($fila['titulo'] ?? RevisionConflictosProgramacion::ETIQUETAS[$fila['tipo']]).($nombre ? ' · '.$nombre : ''),
                'dia' => $fila['dia'] ?? null, 'intervalo' => $fila['intervalo'] ?? null,
            ];
        }

        foreach ($horarios as $horario) {
            if (! $this->esLaboratorio($horario)
                || $horario->asignacionesLaboratorios->isNotEmpty() || $horario->asignacionesLaboratoriosMoviles->isNotEmpty()) continue;
            $filas[] = ['tipo' => 'recursos', 'titulo' => 'Clase sin laboratorio', 'mensaje' => 'Falta asignar un laboratorio a esta clase.',
                'horarios' => [(int) $horario->id], 'dia' => $horario->dia?->nombre, 'intervalo' => $this->horas($horario->bloqueHorario), 'nivel' => 'rojo'];
        }

        // En el panel también interesa que una cohorte no tenga dos ramos distintos a la misma hora.
        // Indexar por día y semestre limita la comparación a los bloques que pueden coincidir.
        $porDiaSemestre = $horarios->groupBy(fn ($horario) => $horario->dia_id.':'.$horario->actividadSeccion->seccion->semestre);
        foreach ($porDiaSemestre as $eventos) {
            $porInicio = $eventos->map(fn ($horario) => ['horario' => $horario, 'intervalo' => IntervalosHorario::deBloque($horario->bloqueHorario)])
                ->filter(fn ($evento) => $evento['intervalo'])->sortBy(fn ($evento) => $evento['intervalo'][0]);
            $activos = [];
            foreach ($porInicio as $evento) {
                $horario = $evento['horario'];
                [$inicio, $fin] = $evento['intervalo'];
                $activos = array_filter($activos, fn ($anterior) => $anterior['intervalo'][1] > $inicio);
                foreach ($activos as $anterior) {
                    $otro = $anterior['horario'];
                    $clave = $this->clavePar($horario->id, $otro->id);
                    if (isset($pares[$clave]) || ! MovimientosPanelCarrera::comparteEstudiantes($horario, $otro)) continue;
                    $desde = gmdate('H:i', $inicio);
                    $hasta = gmdate('H:i', min($fin, $anterior['intervalo'][1]));
                    $filas[] = ['tipo' => 'conflicto', 'titulo' => 'Cruce de estudiantes', 'mensaje' => 'Dos clases de la misma cohorte coinciden en este intervalo.',
                        'horarios' => [(int) $otro->id, (int) $horario->id], 'dia' => $horario->dia?->nombre, 'intervalo' => $desde.'–'.$hasta, 'nivel' => 'rojo'];
                    $pares[$clave] = true;
                }
                $activos[] = $evento;
            }
        }

        return $filas;
    }

    private function esLaboratorio(Models\Horario $horario): bool
    {
        return $this->actividad($horario->actividadSeccion?->tipo) === 'Laboratorio'
            || $horario->asignacionesLaboratorios->isNotEmpty() || $horario->asignacionesLaboratoriosMoviles->isNotEmpty();
    }

    private function horas(?Models\BloqueHorario $bloque): ?string
    {
        $rango = IntervalosHorario::deBloque($bloque);

        return $rango ? gmdate('H:i', $rango[0]).'–'.gmdate('H:i', $rango[1]) : null;
    }

    private function clavePar(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }

    private function alertas(array $observaciones): array
    {
        $alertas = [];
        foreach ($observaciones as $fila) {
            foreach ($fila['horarios'] as $id) {
                $alertas[$id]['nivel'] = ($alertas[$id]['nivel'] ?? '') === 'rojo' ? 'rojo' : $fila['nivel'];
                $alertas[$id]['motivos'][] = $fila['mensaje'];
            }
        }

        return $alertas;
    }

    private function indicadores(array $secciones, array $horarios, array $observaciones): array
    {
        $grupos = collect($secciones)->flatMap(fn ($fila) => [...$fila['grupos'], ...$fila['carga_sin_actividad'], ...$fila['carga_sin_grupos']]);
        $actividades = $grupos->groupBy('actividad')->map(fn ($filas, $tipo) => [
            'tipo' => $tipo, 'requeridos' => $filas->sum('requeridos'), 'asignados' => $filas->sum('asignados'),
            'pendientes' => $filas->sum('pendientes'), 'excedentes' => $filas->sum('excedentes'),
        ])->values()->all();

        return [
            'secciones' => count($secciones), 'con_docente' => count(array_filter(array_column($secciones, 'con_docente'))),
            'carga_completa' => count(array_filter(array_column($secciones, 'carga_completa'))),
            'disponibilidad_aprobada' => count(array_filter(array_column($secciones, 'disponibilidad_aprobada'))),
            'modulos' => count($horarios), 'requeridos' => $grupos->sum('requeridos'), 'pendientes' => $grupos->sum('pendientes'),
            'excedentes' => $grupos->sum('excedentes'), 'coincidencias' => collect($observaciones)->where('tipo', 'conflicto')->count(),
            'por_actividad' => $actividades,
        ];
    }

    private function cargaDocente(Collection $docentes, Collection $horarios, Collection $responsables, array $calendario,
        Collection $declaradas, Collection $aprobados, array $horariosIds, Collection $nombresVersion): array
    {
        $porDocente = [];
        $idsDocentes = array_fill_keys($docentes->pluck('profesor_id')->all(), true);
        foreach ($horarios as $horario) {
            foreach ($responsables->get($horario->id)->unique('profesor_id') as $asignacion) {
                if (isset($idsDocentes[$asignacion->profesor_id])) $porDocente[$asignacion->profesor_id][] = $horario;
            }
        }

        return collect($this->docentesResumen($docentes, $nombresVersion))->map(function ($docente) use ($porDocente, $calendario, $declaradas, $aprobados, $horariosIds) {
            $clases = collect($porDocente[$docente['id']] ?? []);
            $tramos = $clases->groupBy('dia_id')->map(fn ($modulos) => $modulos->map(fn ($horario) => IntervalosHorario::deBloque($horario->bloqueHorario))->filter());
            $semanal = $declaradas->get($docente['id'], collect());
            $porDia = $semanal->groupBy('dia_id');
            $esperas = $estados = $externos = [];
            foreach ($calendario as $clave => $celda) {
                $rangos = $tramos->get($celda['dia'], collect());
                $estados[$clave] = $this->estadoDisponibilidad($celda['intervalo'], $semanal->isNotEmpty(), $porDia->get($celda['dia'], collect()));
                if (! $celda['almuerzo'] && $this->esEspera($celda['intervalo'], $rangos)) $esperas[] = $clave;
            }
            $propios = [];
            foreach ($clases as $horario) {
                if (isset($horariosIds[$horario->id])) {
                    $propios[] = (int) $horario->id;
                } else {
                    $clave = $horario->dia_id.':'.$horario->bloque_horario_id;
                    $externos[$clave] = ($externos[$clave] ?? 0) + 1;
                }
            }

            return $docente + [
                'dias' => $tramos->count(), 'modulos' => $clases->count(), 'huecos' => count($esperas),
                'dias_cortos' => $tramos->filter(fn (Collection $rangos) => $rangos->count() > 0 && $rangos->count() <= 2)->count(),
                'fuera' => $clases->filter(function ($horario) use ($semanal, $porDia) {
                    $rango = IntervalosHorario::deBloque($horario->bloqueHorario);
                    return $rango && $this->estadoDisponibilidad($rango, $semanal->isNotEmpty(), $porDia->get($horario->dia_id, collect())) === 'fuera';
                })->count(),
                'disponibilidad' => $aprobados->has($docente['id']) ? 'aprobada' : ($semanal->isNotEmpty() ? 'registrada' : 'sin_declarar'),
                'horarios' => $propios, 'calendario' => $estados, 'esperas' => $esperas, 'ocupaciones_externas' => $externos,
            ];
        })->sortBy(fn ($fila) => [-$fila['fuera'], -$fila['dias'], -$fila['huecos'], $fila['nombre']])->values()->all();
    }

    private function estadoDisponibilidad(array $intervalo, bool $declaro, Collection $registros): string
    {
        if (! $declaro) return 'sin_declarar';

        return IntervalosHorario::coberturaDisponibilidad($intervalo, $registros) === 'cubierto' ? 'disponible' : 'fuera';
    }

    private function esEspera(array $intervalo, Collection $rangos): bool
    {
        return $rangos->contains(fn ($r) => $r[1] <= $intervalo[0])
            && $rangos->contains(fn ($r) => $r[0] >= $intervalo[1])
            && ! $rangos->contains(fn ($r) => IntervalosHorario::seSuperponen($intervalo, $r));
    }

    private function ocupacionLaboratorios(Models\Carrera $carrera, Collection $horarios, Collection $horariosCarrera, array $calendario, array $horariosIds): array
    {
        $sedes = $carrera->sedes()->pluck('sedes.id')->push($carrera->sede_id)->filter()->unique();
        $fijosUsados = $horariosCarrera->flatMap->asignacionesLaboratorios->pluck('laboratorio_id')->unique();
        $movilesUsados = $horariosCarrera->flatMap->asignacionesLaboratoriosMoviles->pluck('laboratorio_movil_id')->unique();
        $fijos = Models\Laboratorio::whereHas('sala', fn ($sala) => $sala->where('universidad_id', $carrera->universidad_id))
            ->where(fn ($query) => $query->where(fn ($activos) => $activos->where('estado', true)->whereHas('sala', fn ($sala) => $sala->whereIn('sede_id', $sedes)))
                ->orWhereIn('id', $fijosUsados))->with('sala.sede')->orderBy('nombre')->get();
        $moviles = Models\LaboratorioMovil::where('universidad_id', $carrera->universidad_id)
            ->where(fn ($query) => $query->where(fn ($activos) => $activos->where('estado', true)->whereIn('sede_id', $sedes))
                ->orWhereIn('id', $movilesUsados))->with('sede')->orderBy('nombre')->get();
        $fijosPorSala = $fijos->groupBy('sala_id');
        $reservas = [];
        foreach ($horarios as $horario) {
            $intervalo = IntervalosHorario::deBloque($horario->bloqueHorario);
            if (! $intervalo) continue;
            $salas = $horario->asignacionesSalas->pluck('sala_id')->concat($horario->asignacionesLaboratorios->pluck('laboratorio.sala_id'))->filter()->unique();
            $claves = $salas->flatMap(fn ($sala) => $fijosPorSala->get($sala, collect())->map(fn ($laboratorio) => 'fijo:'.$laboratorio->id))
                ->concat($horario->asignacionesLaboratoriosMoviles->map(fn ($asignacion) => 'movil:'.$asignacion->laboratorio_movil_id))->unique();
            foreach ($claves as $clave) $reservas[$clave][$horario->dia_id][$horario->id] = $intervalo;
        }

        return $fijos->toBase()->concat($moviles)->map(function ($laboratorio) use ($calendario, $reservas, $horariosIds) {
            $tipo = $laboratorio instanceof Models\Laboratorio ? 'fijo' : 'movil';
            $uso = $reservas[$tipo.':'.$laboratorio->id] ?? [];
            $celdas = [];
            foreach ($calendario as $clave => $celda) {
                $cruces = array_keys(array_filter($uso[$celda['dia']] ?? [], fn ($rango) => IntervalosHorario::seSuperponen($rango, $celda['intervalo'])));
                $propios = array_values(array_filter($cruces, fn ($id) => isset($horariosIds[$id])));
                $celdas[$clave] = ['horarios' => $propios, 'ocupado' => $cruces !== [], 'otras_carreras' => count($cruces) - count($propios)];
            }
            $ocupados = count(array_filter(array_column($celdas, 'ocupado')));
            $total = count($celdas);
            $sede = $tipo === 'fijo' ? $laboratorio->sala?->sede : $laboratorio->sede;
            $disponible = $laboratorio->estado && $laboratorio->disponible
                && ($tipo === 'movil' || ($laboratorio->sala?->estado && $laboratorio->sala?->disponible));

            return [
                'id' => (int) $laboratorio->id, 'tipo' => $tipo, 'nombre' => $laboratorio->nombre, 'sede' => $sede?->nombre,
                'capacidad' => (int) $laboratorio->capacidad, 'ocupados' => $ocupados, 'libres' => $disponible ? $total - $ocupados : 0,
                'total' => $total, 'disponible' => $disponible, 'bloques' => $celdas,
            ];
        })->all();
    }
}
