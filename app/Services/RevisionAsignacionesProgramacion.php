<?php

namespace App\Services;

use App\Models\Horario;
use Illuminate\Support\Collection;

class RevisionAsignacionesProgramacion
{
    public function revisar(Horario $horario, Collection $docentes): array
    {
        $salas = $laboratorios = $moviles = [];
        $agregar = function (array &$lista, string $tipo, $modelo, $asignada = null): void {
            if (! $modelo) return;
            $anterior = $lista[$modelo->id]['asignada'] ?? 0;
            $lista[$modelo->id] = ['tipo' => $tipo, 'id' => $modelo->id,
                'nombre' => $modelo->codigo ?: ($modelo->nombre ?: '#'.$modelo->id),
                'capacidad' => (int) $modelo->capacidad, 'asignada' => max($anterior, (int) $asignada)];
        };
        foreach ($horario->asignacionesSalas as $asignacion) $agregar($salas, 'sala', $asignacion->sala, $asignacion->capacidad_asignada);
        foreach ($horario->asignacionesLaboratorios as $asignacion) {
            $agregar($laboratorios, 'laboratorio', $asignacion->laboratorio, $asignacion->capacidad_asignada);
            $agregar($salas, 'sala', $asignacion->laboratorio?->sala, $asignacion->capacidad_asignada);
        }
        foreach ($horario->asignacionesLaboratoriosMoviles as $asignacion) $agregar($moviles, 'movil', $asignacion->laboratorioMovil, $asignacion->capacidad_asignada);

        $pendientes = [];
        if (! $docentes->contains(fn ($asignacion) => $asignacion->profesor !== null)) $pendientes[] = 'Falta asignar un docente a este módulo.';
        if (! $salas) $pendientes[] = $moviles
            ? 'El laboratorio móvil necesita una sala física asignada.'
            : 'Falta asignar una sala o un laboratorio con sala física.';

        [$cantidad, $origen] = $this->estudiantes($horario);
        $problemas = $afectados = [];
        if ($salas || $laboratorios || $moviles) {
            if ($cantidad === null) {
                $problemas[] = $origen;
                foreach (array_merge($salas, $laboratorios, $moviles) as $recurso) $afectados[$recurso['tipo'].':'.$recurso['id']] = $recurso;
            }
            foreach ([[$salas, 'salas'], [$laboratorios, 'laboratorios'], [$moviles, 'laboratorios móviles']] as [$familia, $nombre]) {
                $problemas = array_merge($problemas, $this->capacidades($familia, $nombre, $cantidad, $afectados));
            }
        }
        if ($problemas && $cantidad !== null) array_unshift($problemas, 'Se requieren '.$cantidad.' plazas según '.$origen.'.');

        return [
            'asignaciones' => $pendientes ? ['mensaje' => implode(' ', $pendientes), 'recursos' => []] : null,
            'capacidad' => $problemas ? ['mensaje' => implode(' ', $problemas), 'recursos' => array_values($afectados)] : null,
        ];
    }

    private function estudiantes(Horario $horario): array
    {
        $actividad = $horario->actividadSeccion;
        $grupo = $horario->grupoSeccion;
        if ($grupo && (int) $grupo->actividad_seccion_id !== (int) $horario->actividad_seccion_id) {
            return [null, 'Revisa el grupo del módulo: no corresponde a la actividad.'];
        }
        foreach (['inscritos' => 'los inscritos del grupo', 'cupos' => 'los cupos del grupo'] as $campo => $origen) {
            if ((int) $grupo?->$campo > 0) return [(int) $grupo->$campo, $origen];
        }
        if (($actividad?->grupos->count() ?? 0) > 1) {
            return [null, 'Informa los inscritos o cupos de este grupo para revisar la capacidad; la actividad tiene varios grupos.'];
        }
        if ((int) $actividad?->cantidad_estudiantes > 0) return [(int) $actividad->cantidad_estudiantes, 'la cantidad de estudiantes de la actividad'];
        foreach (['inscritos' => 'los inscritos de la sección', 'cupos' => 'los cupos de la sección'] as $campo => $origen) {
            if ((int) $actividad?->seccion?->$campo > 0) return [(int) $actividad->seccion->$campo, $origen];
        }
        return [null, 'Falta informar la cantidad de estudiantes o cupos para comprobar la capacidad.'];
    }

    private function capacidades(array $recursos, string $familia, ?int $cantidad, array &$afectados): array
    {
        if (! $recursos) return [];
        $problemas = [];
        $varios = count($recursos) > 1;
        if ($varios) {
            if (collect($recursos)->contains(fn ($recurso) => $recurso['asignada'] <= 0)) {
                $problemas[] = 'Completa la capacidad asignada en cada recurso para revisar la distribución entre '.$familia.'.';
            } elseif ($cantidad !== null && array_sum(array_column($recursos, 'asignada')) < $cantidad) {
                $problemas[] = 'La distribución entre '.$familia.' cubre '.array_sum(array_column($recursos, 'asignada')).' de las '.$cantidad.' plazas requeridas.';
            }
            if ($problemas) foreach ($recursos as $recurso) $afectados[$recurso['tipo'].':'.$recurso['id']] = $recurso;
        }
        foreach ($recursos as $recurso) {
            $capacidad = $recurso['capacidad'];
            $requerida = $varios ? $recurso['asignada'] : max($cantidad ?? 0, $recurso['asignada']);
            if ($capacidad <= 0) {
                $problemas[] = $recurso['nombre'].': capacidad sin informar.';
                $afectados[$recurso['tipo'].':'.$recurso['id']] = $recurso;
            } elseif ($requerida > $capacidad) {
                $problemas[] = $recurso['nombre'].': capacidad '.$capacidad.'; se requieren '.$requerida.' plazas. Revisa la asignación o la capacidad registrada.';
                $afectados[$recurso['tipo'].':'.$recurso['id']] = $recurso;
            }
        }
        return $problemas;
    }
}
