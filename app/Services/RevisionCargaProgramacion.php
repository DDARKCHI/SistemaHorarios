<?php

namespace App\Services;

use App\Models\ActividadSeccion;
use App\Models\GrupoSeccion;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Collection;

class RevisionCargaProgramacion
{
    public function revisar(VersionPlanificacion $version, Collection $horarios): array
    {
        $actividades = ActividadSeccion::where('estado', true)
            ->whereHas('seccion', fn ($query) => $query->where('periodo_academico_id', $version->periodo_academico_id)->where('estado_activo', true))
            ->with(['seccion.asignatura', 'grupos' => fn ($query) => $query->where('estado_activo', true)->orderBy('id')])->orderBy('id')->get()->keyBy('id');
        $grupos = $actividades->flatMap(fn ($actividad) => $actividad->grupos)->keyBy('id');
        $porGrupo = [];
        $observaciones = [];
        $totales = ['actividades' => $actividades->count(), 'grupos' => $grupos->count(), 'requeridos' => 0, 'asignados' => 0,
            'cubiertos' => 0, 'pendientes' => 0, 'excedentes' => 0, 'actividades_sin_grupos' => 0, 'grupos_sin_carga' => 0, 'modulos_sin_grupo' => 0];

        foreach ($horarios as $horario) {
            if (! $horario->estado || (int) $horario->version_planificacion_id !== (int) $version->id) continue;
            $actividad = $actividades->get($horario->actividad_seccion_id);
            // Los módulos de otro período ya se señalan en la revisión de datos.
            if (! $actividad) {
                $actividad = $horario->actividadSeccion;
                if ((int) $actividad?->seccion?->periodo_academico_id === (int) $version->periodo_academico_id) {
                    $observaciones[] = $this->observacion($actividad, $horario->grupoSeccion, 'Asignación fuera de la carga activa',
                        'El horario #'.$horario->id.' pertenece a una actividad o sección inactiva. No se contabiliza en la carga del período.', $horario->id);
                }
                continue;
            }
            $grupo = $grupos->get($horario->grupo_seccion_id);
            if (! $grupo || (int) $grupo->actividad_seccion_id !== (int) $actividad->id) {
                $totales['modulos_sin_grupo']++;
                $observaciones[] = $this->observacion($actividad, null, 'Módulo sin grupo válido',
                    'El horario #'.$horario->id.' no tiene un grupo activo de esta actividad. Corrige su grupo para contabilizarlo en la carga.', $horario->id);
                continue;
            }
            $porGrupo[$grupo->id] = ($porGrupo[$grupo->id] ?? 0) + 1;
        }

        foreach ($actividades as $actividad) {
            if ($actividad->grupos->isEmpty()) {
                $totales['actividades_sin_grupos']++;
                $observaciones[] = $this->observacion($actividad, null, 'Actividad sin grupos activos',
                    'Define los grupos de esta actividad para calcular sus módulos requeridos. No se puede dar su carga por cubierta.');
            }
            foreach ($actividad->grupos as $grupo) {
                // La asignación usa cantidad_horas como módulos requeridos por grupo.
                $requeridos = max(0, (int) $actividad->cantidad_horas);
                $asignados = $porGrupo[$grupo->id] ?? 0;
                $totales['asignados'] += $asignados;
                if ($requeridos === 0) {
                    $totales['grupos_sin_carga']++;
                    $observaciones[] = $this->observacion($actividad, $grupo, 'Carga requerida sin definir',
                        'La actividad no tiene una cantidad de módulos válida. Este grupo tiene '.$asignados
                        .($asignados === 1 ? ' módulo asignado; ' : ' módulos asignados; ').'define la carga antes de comprobar su cumplimiento.');
                    continue;
                }
                $pendientes = max(0, $requeridos - $asignados);
                $excedentes = max(0, $asignados - $requeridos);
                $totales['requeridos'] += $requeridos;
                $totales['cubiertos'] += min($requeridos, $asignados);
                $totales['pendientes'] += $pendientes;
                $totales['excedentes'] += $excedentes;
                if ($pendientes || $excedentes) {
                    $observaciones[] = $this->observacion($actividad, $grupo, $pendientes ? 'Módulos pendientes' : 'Módulos en exceso',
                        'Requeridos: '.$requeridos.($requeridos === 1 ? ' módulo.' : ' módulos.').' Asignados en esta versión: '.$asignados.'. '
                        .($pendientes ? ($pendientes === 1 ? 'Falta 1' : 'Faltan '.$pendientes).' por asignar.' : 'Hay '.$excedentes.' en exceso; revisa la carga o las asignaciones.'));
                }
            }
        }

        return $totales + ['observaciones' => $observaciones];
    }

    private function observacion(ActividadSeccion $actividad, ?GrupoSeccion $grupo, string $titulo, string $mensaje, int $horarioId = 0): array
    {
        $seccion = $actividad->seccion;
        $asignatura = implode(' · ', array_filter([$seccion?->asignatura?->codigo, $seccion?->asignatura?->nombre], fn ($dato) => filled($dato)));

        return ['tipo' => 'carga', 'titulo' => $titulo, 'mensaje' => $mensaje, 'horarios' => [],
            'clase' => ['asignatura' => $asignatura, 'seccion' => $seccion?->codigo ?? 'Sin sección', 'actividad' => $actividad->tipo, 'grupo' => $grupo?->codigo],
            'recursos' => $seccion ? [['tipo' => 'curso', 'id' => (int) $seccion->id, 'nombre' => ($seccion->asignatura?->codigo ?: 'Curso').' · Sección '.$seccion->codigo]] : [],
            'orden' => [0, (int) $actividad->id, (int) ($grupo?->id ?? $horarioId)]];
    }
}
