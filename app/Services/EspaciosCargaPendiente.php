<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EspaciosCargaPendiente
{
    public function candidatos(Models\GrupoSeccion $grupo, int $sedeId, Collection $docentes, Collection $salas, Collection $moviles,
        RevisionRecursosProgramacion $revisionRecursos, PrioridadUsoRecursos $prioridades, Collection $inventario, int &$intentos): array
    {
        $actividad = $grupo->actividadSeccion;
        $familias = $actividad->requerimientos->map(fn ($r) => Models\RequerimientoActividad::tipoNormalizado($r->tipo_requerimiento));
        $requiereLab = $familias->contains('Laboratorio') || Models\RequerimientoActividad::tipoNormalizado($actividad->tipo) === 'Laboratorio';
        $requiereMovil = $familias->contains('Laboratorio móvil');
        $alternativaMovil = $requiereLab && ! $familias->contains('Laboratorio');
        $movilesSede = $requiereMovil || $alternativaMovil
            ? $moviles->filter(fn ($m) => (int) $m->sede_id === $sedeId && $m->estado && $m->disponible)->values()
            : collect();
        if (! $requiereMovil) $movilesSede->prepend(null);
        $cantidad = (int) ($grupo->inscritos ?: $grupo->cupos);
        if (! $cantidad && $actividad->grupos->where('estado_activo', true)->count() <= 1) $cantidad = (int) ($actividad->cantidad_estudiantes ?: ($actividad->seccion->inscritos ?: $actividad->seccion->cupos));
        $resultado = [];
        foreach ($salas as $sala) {
            if ((int) $sala->sede_id !== $sedeId || ! $sala->estado || ! $sala->disponible || ! $sala->tipoSala?->estado || ! $sala->sede?->estado) continue;
            $laboratorios = $sala->laboratorios->filter(fn ($l) => $l->estado && $l->disponible);
            if ($sala->laboratorios->isEmpty() && (! $requiereLab || $alternativaMovil)) $laboratorios = collect([null]);
            foreach ($laboratorios as $lab) foreach ($movilesSede as $movil) {
                if ($requiereLab && ! $lab && (! $alternativaMovil || ! $movil)) continue;
                if ($lab && $movil && ! $requiereMovil) continue;
                if (++$intentos > 30000) throw ValidationException::withMessages(['generacion' => 'La revisión de recursos alcanzó su límite. Reduce los grupos o precisa sus requerimientos para preparar una propuesta más acotada.']);
                $virtual = new Models\Horario(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id, 'estado' => true]);
                $virtual->setRelation('actividadSeccion', $actividad)->setRelation('grupoSeccion', $grupo)->setRelation('profesores', $docentes);
                $asSala = new Models\AsignacionSala(['sala_id' => $sala->id, 'capacidad_asignada' => $cantidad, 'es_principal' => true, 'estado' => true]); $asSala->setRelation('sala', $sala);
                $asLabs = $asMoviles = collect();
                if ($lab) { $asLab = new Models\AsignacionLaboratorio(['laboratorio_id' => $lab->id, 'capacidad_asignada' => $cantidad, 'es_principal' => true, 'estado' => true]); $asLab->setRelation('laboratorio', $lab); $asLabs = collect([$asLab]); }
                if ($movil) { $asMovil = new Models\AsignacionLaboratorioMovil(['laboratorio_movil_id' => $movil->id, 'capacidad_asignada' => $cantidad, 'es_principal' => true, 'estado' => true]); $asMovil->setRelation('laboratorioMovil', $movil); $asMoviles = collect([$asMovil]); }
                $virtual->setRelation('asignacionesSalas', collect([$asSala]))->setRelation('asignacionesLaboratorios', $asLabs)->setRelation('asignacionesLaboratoriosMoviles', $asMoviles);
                $revision = (new RevisionAsignacionesProgramacion)->revisar($virtual, $docentes);
                if ($revision['asignaciones'] || $revision['capacidad'] || $revisionRecursos->revisar($virtual)) continue;
                $recursos = ['sala:'.$sala->id]; $referencias = [['tipo' => 'sala', 'id' => $sala->id]];
                if ($lab) { $recursos[] = 'laboratorio:'.$lab->id; $referencias[] = ['tipo' => 'laboratorio', 'id' => $lab->id]; }
                if ($movil) { $recursos[] = 'movil:'.$movil->id; $referencias[] = ['tipo' => 'movil', 'id' => $movil->id]; }
                foreach ($actividad->requerimientos as $r) if ($r->equipamiento_id && ($e = $inventario->get($r->equipamiento_id))) $referencias[] = ['tipo' => 'equipamiento', 'id' => $e->id, 'tipo_equipamiento' => $e->tipo];
                $niveles = collect($referencias)->map(fn ($r) => $prioridades->nivel($virtual, $r))->filter(fn ($v) => $v !== null);
                $capacidad = min($sala->capacidad, $lab?->capacidad ?? $sala->capacidad, $movil?->capacidad ?? $sala->capacidad);
                $clave = 's'.$sala->id.':l'.($lab?->id ?? 0).':m'.($movil?->id ?? 0);
                $resultado[$clave] = ['sala_id' => (int) $sala->id, 'sede_id' => $sedeId, 'laboratorio_id' => $lab?->id, 'movil_id' => $movil?->id,
                    'cantidad' => $cantidad, 'recursos' => $recursos, 'prioridad' => $niveles->min() ?? 256,
                    'preferencia' => ($lab && ! $requiereLab ? 20 : 0) + ($alternativaMovil && $movil ? 40 : 0) + ($capacidad - $cantidad) / $capacidad,
                    'nombre' => $sala->codigo.($lab ? ' · '.$lab->nombre : '').($movil ? ' · '.$movil->nombre : '').' · '.$sala->sede->nombre];
            }
        }
        ksort($resultado);
        return $resultado;
    }
}
