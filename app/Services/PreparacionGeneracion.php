<?php

namespace App\Services;

use App\Models\{BloqueHorario, Dia, DisponibilidadProfesor, Horario, PeriodoAcademico, RespuestaDisponibilidad, Seccion, VersionPlanificacion};
use App\Support\IntervalosHorario;

class PreparacionGeneracion
{
    public function revisar(PeriodoAcademico $periodo, ?VersionPlanificacion $version): array
    {
        $observaciones = [];
        $agregar = function (string $tipo, string $titulo, string $detalle, ?string $url = null) use (&$observaciones): void {
            $observaciones[] = compact('tipo', 'titulo', 'detalle', 'url');
        };
        if (! $periodo->estado) $agregar('bloqueo', 'Período inactivo', 'Activa el período antes de preparar una nueva programación.');
        $dias = Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get();
        $planificables = $bloques->filter(function ($bloque) {
            $rango = IntervalosHorario::deBloque($bloque);
            return $rango && ! $bloque->es_almuerzo && ! IntervalosHorario::seSuperponen($rango, [13 * 3600, 14 * 3600]);
        });
        if ($dias->isEmpty() || $planificables->isEmpty()) $agregar('bloqueo', 'Calendario sin módulos utilizables', 'Se necesitan días y módulos activos fuera del almuerzo.');
        if ($bloques->contains(fn ($bloque) => ! IntervalosHorario::deBloque($bloque))) $agregar('bloqueo', 'Horas de módulos inválidas', 'Corrige los módulos activos cuyo intervalo está incompleto o termina antes de comenzar.');

        $secciones = Seccion::where('periodo_academico_id', $periodo->id)->where('estado_activo', true)->with([
            'asignatura', 'profesores' => fn ($q) => $q->where('estado', true), 'profesores.profesor',
            'actividades' => fn ($q) => $q->where('estado', true),
            'actividades.grupos' => fn ($q) => $q->where('estado_activo', true),
            'actividades.grupos.profesores' => fn ($q) => $q->where('estado', true), 'actividades.grupos.profesores.profesor',
        ])->orderBy('id')->get();
        if ($secciones->isEmpty()) $agregar('bloqueo', 'Sin secciones activas', 'El período no tiene secciones activas para planificar.');
        // La versión seleccionada aporta los docentes asignados directamente a cada módulo.
        $horarios = $version ? Horario::where('version_planificacion_id', $version->id)->where('estado', true)
            ->with(['profesores' => fn ($q) => $q->where('estado', true), 'profesores.profesor'])->get() : collect();
        $porGrupo = $horarios->groupBy('grupo_seccion_id');
        $docentes = collect();
        $totalGrupos = $totalModulos = $totalActividades = 0;
        foreach ($secciones as $seccion) {
            $curso = ($seccion->asignatura?->codigo ?? 'Ramo').' · '.$seccion->asignatura?->nombre.' · Sección '.$seccion->codigo;
            if (! $seccion->asignatura?->estado || (int) $seccion->asignatura?->universidad_id !== (int) $periodo->universidad_id) {
                $agregar('bloqueo', 'Asignatura por revisar', $curso.': verifica su estado y su institución.');
            }
            if ($seccion->actividades->isEmpty()) $agregar('bloqueo', 'Sección sin actividades', $curso.': define sus actividades antes de generar.');
            foreach ($seccion->actividades as $actividad) {
                $totalActividades++;
                $contexto = $curso.' · '.$actividad->tipo;
                if ($actividad->grupos->isEmpty()) $agregar('bloqueo', 'Actividad sin grupos', $contexto.': falta un grupo activo.');
                if ((int) $actividad->cantidad_horas <= 0) $agregar('bloqueo', 'Carga sin definir', $contexto.': indica los módulos requeridos por grupo.');
                foreach ($actividad->grupos as $grupo) {
                    $totalGrupos++;
                    $totalModulos += max(0, (int) $actividad->cantidad_horas);
                    // Mantener la precedencia de docentes de la revisión actual: módulo, grupo, sección.
                    $asignados = collect();
                    foreach ($porGrupo->get($grupo->id, collect()) as $horario) {
                        $asignados = $asignados->concat($horario->profesores->isNotEmpty() ? $horario->profesores : ($grupo->profesores->isNotEmpty() ? $grupo->profesores : $seccion->profesores));
                    }
                    if ($asignados->isEmpty()) $asignados = $grupo->profesores->isNotEmpty() ? $grupo->profesores : $seccion->profesores;
                    if ($asignados->isEmpty()) $agregar('bloqueo', 'Grupo sin docente', $contexto.' · '.$grupo->codigo.': asigna al menos un docente.', route('programacion.asignacion.profesores', ['periodo_id' => $periodo->id]));
                    foreach ($asignados->unique('profesor_id') as $asignado) $docentes->put($asignado->profesor_id, $asignado->profesor);
                }
            }
        }
        $respuestas = RespuestaDisponibilidad::where('respuesta', 'Aprobada')->whereIn('profesor_id', $docentes->keys())
            ->whereHas('solicitudDisponibilidad', fn ($q) => $q->where('periodo_academico_id', $periodo->id)->where('estado', 'Aprobada'))
            ->orderByDesc('id')->get()->unique('profesor_id')->keyBy('profesor_id');
        $declaradas = DisponibilidadProfesor::where('periodo_academico_id', $periodo->id)->where('estado', true)
            ->whereIn('profesor_id', $docentes->keys())->with('bloqueHorario')->get()->groupBy('profesor_id');
        $firma = hash('sha256', json_encode([
            $dias->map(fn ($dia) => [$dia->id, $dia->numero, $dia->nombre])->values()->all(),
            $bloques->map(fn ($bloque) => [$bloque->id, $bloque->hora_inicio, $bloque->hora_fin, $bloque->es_almuerzo])->values()->all(),
        ], JSON_THROW_ON_ERROR));
        $aprobados = 0;
        foreach ($docentes as $id => $docente) {
            $nombre = $docente ? trim($docente->apellido.', '.$docente->nombre) : 'Docente #'.$id;
            if (! $docente?->estado || (int) $docente?->universidad_id !== (int) $periodo->universidad_id) {
                $agregar('bloqueo', 'Docente no habilitado', $nombre.': revisa su estado y su institución.'); continue;
            }
            $respuesta = $respuestas->get($id);
            if (! $respuesta?->bloques || ($respuesta->bloques['firma'] ?? null) !== $firma) {
                $agregar('bloqueo', 'Disponibilidad aprobada pendiente', $nombre.': falta una respuesta aprobada para el calendario y período actuales.', route('disponibilidad.index', ['periodo_id' => $periodo->id])); continue;
            }
            $actual = $declaradas->get($id, collect());
            $esperadas = collect($respuesta->bloques['calendario'] ?? [])->mapWithKeys(fn ($clave) => [$clave => in_array($clave, $respuesta->bloques['disponibles'] ?? [], true) ? 'Disponible' : 'No disponible'])->sortKeys()->all();
            $registradas = $actual->mapWithKeys(fn ($d) => [$d->dia_id.':'.$d->bloque_horario_id => $d->tipo_disponibilidad])->sortKeys()->all();
            if ($esperadas !== $registradas) {
                $agregar('bloqueo', 'Disponibilidad modificada tras la aprobación', $nombre.': los bloques vigentes difieren de la respuesta aprobada. Solicita una nueva respuesta.', route('disponibilidad.index', ['periodo_id' => $periodo->id])); continue;
            }
            $utilizable = $dias->contains(fn ($dia) => $planificables->contains(fn ($bloque) => IntervalosHorario::coberturaDisponibilidad(IntervalosHorario::deBloque($bloque), $actual->where('dia_id', $dia->id)) === 'cubierto'));
            if (! $utilizable) $agregar('bloqueo', 'Sin disponibilidad fuera del almuerzo', $nombre.': la respuesta aprobada no permite asignar ningún módulo automático.', route('disponibilidad.index', ['periodo_id' => $periodo->id]));
            else $aprobados++;
        }
        $revision = null;
        if (! $version) $agregar('bloqueo', 'Falta una versión base', 'Selecciona una versión del período para revisar sus asignaciones y recursos.');
        elseif ((int) $version->periodo_academico_id !== (int) $periodo->id) throw new \InvalidArgumentException('La versión no pertenece al período.');
        else {
            $revision = app(RevisionConflictosProgramacion::class)->revisar($version);
            foreach (['asignaciones' => 'Asignaciones incompletas', 'capacidad' => 'Capacidad de espacios', 'recursos' => 'Compatibilidad de recursos', 'datos' => 'Datos de horarios', 'carga_observaciones' => 'Carga de la versión base'] as $clave => $titulo) {
                if ($revision[$clave] > 0) $agregar('bloqueo', $titulo, 'La versión base tiene '.$revision[$clave].' observaciones de este tipo. Revísalas antes de reutilizar sus asignaciones.', $version->tipo === 'semestral' ? route('programacion.validacion', ['periodo_id' => $periodo->id, 'version_id' => $version->id]) : null);
            }
            if ($revision['conflictos'] > 0) $agregar('aviso', 'Topes en la versión base', 'Hay '.$revision['conflictos'].' topes. Una reorganización deberá resolverlos; la revisión previa no garantiza que exista una solución.');
            if ($revision['disponibilidad'] > 0) $agregar('aviso', 'Horarios fuera de disponibilidad', 'Hay '.$revision['disponibilidad'].' observaciones de disponibilidad en la base; esos módulos necesitan otra ubicación horaria.');
        }
        return ['secciones' => $secciones->count(), 'actividades' => $totalActividades, 'grupos' => $totalGrupos, 'modulos' => $totalModulos,
            'docentes' => $docentes->count(), 'aprobados' => $aprobados, 'bloques_utilizables' => $planificables->count() * $dias->count(),
            'bloqueos' => collect($observaciones)->where('tipo', 'bloqueo')->count(), 'observaciones' => collect($observaciones), 'revision' => $revision];
    }
}
