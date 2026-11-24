<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EspaciosCambioHorario
{
    public function preparar(Models\Horario $horario, Collection $docentes, Collection $inventario, string $seleccion): array
    {
        $salas = $horario->asignacionesSalas->map(fn ($a) => $a->sala)->concat($horario->asignacionesLaboratorios->map(fn ($a) => $a->laboratorio?->sala))->filter()->unique('id');
        $nombres = $salas->map(fn ($s) => $s->codigo)->implode(' / ');
        $opciones = ['actual' => ['clave' => 'actual', 'nombre' => 'Conservar espacios actuales: '.$nombres, 'sala_id' => null, 'laboratorio_id' => null, 'firma' => null]];
        $simulados = ['actual' => $horario];
        $limitacion = null;
        if ($salas->count() !== 1 || $horario->asignacionesSalas->count() > 1 || $horario->asignacionesLaboratorios->count() > 1) {
            $limitacion = 'Esta clase distribuye su capacidad entre varios espacios. Puedes cambiar el módulo conservándolos; la redistribución de espacios múltiples debe revisarse por separado.';
        } else {
            $base = $salas->first();
            $laboratorio = $horario->asignacionesLaboratorios->isNotEmpty();
            $consulta = Models\Sala::where('universidad_id', $base->universidad_id)->where('sede_id', $base->sede_id)->where('tipo_sala_id', $base->tipo_sala_id)
                ->where('estado', true)->where('disponible', true)->whereHas('sede', fn ($q) => $q->where('estado', true))->with('tipoSala', 'sede')->orderBy('codigo')->orderBy('id');
            $candidatos = $laboratorio ? Models\Laboratorio::where('estado', true)->where('disponible', true)->whereIn('sala_id', (clone $consulta)->select('id'))->with('sala.tipoSala', 'sala.sede')->orderBy('nombre')->orderBy('id')->get() : $consulta->get();
            $revisionRecursos = new RevisionRecursosProgramacion((int) $base->universidad_id, new RevisionEquipamientoProgramacion($inventario));
            foreach ($candidatos as $candidato) {
                $sala = $laboratorio ? $candidato->sala : $candidato;
                if ((! $laboratorio && (int) $sala->id === (int) $base->id) || ($laboratorio && (int) $candidato->id === (int) $horario->asignacionesLaboratorios->first()->laboratorio_id)) continue;
                $virtual = $this->simular($horario, $sala, $laboratorio ? $candidato : null);
                $revision = (new RevisionAsignacionesProgramacion)->revisar($virtual, $docentes);
                if ($revision['asignaciones'] || $revision['capacidad'] || $revisionRecursos->revisar($virtual)) continue;
                $clave = ($laboratorio ? 'laboratorio:' : 'sala:').$candidato->id;
                $nombre = $laboratorio ? $candidato->nombre.' · '.$sala->codigo : $sala->codigo.($sala->nombre && $sala->nombre !== $sala->codigo ? ' · '.$sala->nombre : '');
                $opciones[$clave] = ['clave' => $clave, 'nombre' => $nombre.' · '.$sala->sede->nombre.' · '.$sala->capacidad.' plazas',
                    'sala_id' => $sala->id, 'laboratorio_id' => $laboratorio ? $candidato->id : null,
                    'firma' => hash('sha256', json_encode([$sala->getAttributes(), $sala->tipoSala?->getAttributes(), $sala->sede->getAttributes(), $laboratorio ? $candidato->getAttributes() : null], JSON_THROW_ON_ERROR))];
                $simulados[$clave] = $virtual;
            }
        }
        if (! isset($opciones[$seleccion])) throw ValidationException::withMessages(['espacio' => 'El espacio elegido ya no es compatible, no pertenece a la misma sede o dejó de estar habilitado. Selecciona otro espacio.']);
        return ['espacios' => $opciones, 'espacio' => $seleccion, 'destino' => $opciones[$seleccion], 'candidato' => $simulados[$seleccion], 'limitacionEspacios' => $limitacion];
    }

    private function simular(Models\Horario $origen, Models\Sala $sala, ?Models\Laboratorio $laboratorio): Models\Horario
    {
        $virtual = clone $origen;
        $asignadasSalas = collect();
        if ($origen->asignacionesSalas->isNotEmpty()) {
            $registro = clone $origen->asignacionesSalas->first();
            $registro->sala_id = $sala->id; $registro->setRelation('sala', $sala); $asignadasSalas->push($registro);
        }
        $asignadosLabs = collect();
        if ($laboratorio) {
            $registro = clone $origen->asignacionesLaboratorios->first();
            $registro->laboratorio_id = $laboratorio->id; $registro->setRelation('laboratorio', $laboratorio); $asignadosLabs->push($registro);
        }
        return $virtual->setRelation('asignacionesSalas', $asignadasSalas)->setRelation('asignacionesLaboratorios', $asignadosLabs);
    }
}
