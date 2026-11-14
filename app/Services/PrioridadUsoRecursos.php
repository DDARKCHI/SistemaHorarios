<?php

namespace App\Services;

use App\Models\Horario;
use App\Models\PrioridadRecurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PrioridadUsoRecursos
{
    public function __construct(private Collection $reglas, private ?Collection $inventario = null) {}

    public function nivel(Horario $horario, array $recurso): ?int
    {
        $curso = $horario->actividadSeccion?->seccion?->asignatura;
        if (!$curso || !$curso->estado) return null;
        $tipoSala = null;
        $tiposEquipo = isset($recurso['tipo_equipamiento']) ? [$recurso['tipo_equipamiento']] : [];
        $salaId = null;
        if ($recurso['tipo'] === 'sala') {
            $tipoSala = $horario->asignacionesSalas->firstWhere('sala_id', $recurso['id'])?->sala?->tipo_sala_id;
            if (!$tipoSala) $tipoSala = $horario->asignacionesLaboratorios->first(fn ($a) => (int) $a->laboratorio?->sala_id === (int) $recurso['id'])?->laboratorio?->sala?->tipo_sala_id;
        } elseif ($recurso['tipo'] === 'laboratorio') {
            $sala = $horario->asignacionesLaboratorios->firstWhere('laboratorio_id', $recurso['id'])?->laboratorio?->sala;
            $tipoSala = $sala?->tipo_sala_id;
            $salaId = $sala?->id;
        }
        if ($salaId && $this->inventario) {
            foreach ($this->inventario as $equipo) {
                if ($equipo->estado && $equipo->disponible && (int) $equipo->universidad_id === (int) $curso->universidad_id
                    && $equipo->ubicaciones->contains(fn ($u) => (int) $u->sala_id === (int) $salaId && $u->cantidad > 0)) $tiposEquipo[] = $equipo->tipo;
            }
        }
        $tiposEquipo = array_map(fn ($tipo) => Str::lower(Str::ascii($tipo)), $tiposEquipo);
        $coincidentes = $this->reglas->filter(fn ($r) => $r->estado
            && (int) $r->asignatura_id === (int) $curso->id && $r->tipo_recurso === $recurso['tipo']
            && (!$r->tipo_sala_id || ($r->tipoSala?->estado && (int) $r->tipo_sala_id === (int) $tipoSala))
            && (!$r->tipo_equipamiento || in_array(Str::lower(Str::ascii($r->tipo_equipamiento)), $tiposEquipo, true)));
        if ($coincidentes->isEmpty()) return null;
        // La regla del tipo específico prevalece sobre una regla general del mismo curso.
        $detalle = fn ($r) => (int) (bool) $r->tipo_sala_id + (int) (bool) $r->tipo_equipamiento;
        $mayorDetalle = $coincidentes->max($detalle);
        return (int) $coincidentes->filter(fn ($r) => $detalle($r) === $mayorDetalle)->min('nivel');
    }

    public function recomendacion(Horario $a, Horario $b, array $recurso): ?string
    {
        if (!in_array($recurso['tipo'], array_keys(PrioridadRecurso::RECURSOS), true)) return null;
        $cursoA = $a->actividadSeccion?->seccion?->asignatura;
        $cursoB = $b->actividadSeccion?->seccion?->asignatura;
        if (!$cursoA || !$cursoB || $cursoA->id === $cursoB->id) return null;
        $nivelA = $this->nivel($a, $recurso); $nivelB = $this->nivel($b, $recurso);
        if ($nivelA === null && $nivelB === null) return null;
        $etiquetaA = trim($cursoA->codigo.' · '.$cursoA->nombre);
        $etiquetaB = trim($cursoB->codigo.' · '.$cursoB->nombre);
        if ($nivelA === $nivelB) return 'Prioridad de uso de '.$recurso['nombre'].': '.$etiquetaA.' y '.$etiquetaB.' comparten nivel '.$nivelA.'; no hay preferencia entre ellos.';
        $ganaA = ($nivelA ?? PHP_INT_MAX) < ($nivelB ?? PHP_INT_MAX);
        $ganador = $ganaA ? $etiquetaA : $etiquetaB;
        $otro = $ganaA ? $etiquetaB : $etiquetaA;
        $nivel = $ganaA ? $nivelA : $nivelB;
        $nivelOtro = $ganaA ? $nivelB : $nivelA;
        return 'Preferencia de uso de '.$recurso['nombre'].': '.$ganador.' (nivel '.$nivel.') tiene prioridad sobre '.$otro
            .($nivelOtro === null ? ' (sin prioridad configurada)' : ' (nivel '.$nivelOtro.')')
            .'. Revisa la asignación del curso de menor prioridad; los horarios no se modificaron.';
    }
}
