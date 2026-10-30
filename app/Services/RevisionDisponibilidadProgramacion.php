<?php

namespace App\Services;

use App\Models\Horario;
use Illuminate\Support\Collection;

class RevisionDisponibilidadProgramacion
{
    public function __construct(private Collection $disponibilidades, private Collection $docentesVersion) {}

    public function revisar(Horario $horario, Collection $docentes): ?array
    {
        $objetivo = $this->rango($horario);
        if (! $objetivo || ! $horario->dia) return null;

        $mensajes = $recursos = [];
        foreach ($docentes->unique('profesor_id') as $asignacion) {
            $declaradas = $this->disponibilidades->get($asignacion->profesor_id, collect());
            $resultado = $declaradas->isEmpty() ? 'sin_declaracion'
                : $this->cobertura($objetivo, $declaradas->where('dia_id', $horario->dia_id));
            if ($resultado === 'cubierto') continue;

            $profesor = $this->docentesVersion->get($asignacion->profesor_id) ?? $asignacion->profesor;
            $nombre = trim(($profesor?->nombre ?? '').' '.($profesor?->apellido ?? '')) ?: 'Docente #'.$asignacion->profesor_id;
            $mensajes[] = $nombre.': '.match ($resultado) {
                'sin_declaracion' => 'sin disponibilidad registrada para este período; confirma sus bloques.',
                'datos' => 'la disponibilidad de este día contiene datos incompletos o no reconocidos; revísalos antes de comprobar la cobertura.',
                default => 'el módulo queda fuera de la disponibilidad registrada o coincide con un bloque no disponible.',
            };
            $recursos[] = ['tipo' => 'profesor', 'id' => (int) $asignacion->profesor_id, 'nombre' => $nombre];
        }

        return $mensajes ? ['mensaje' => implode(' ', $mensajes), 'recursos' => $recursos] : null;
    }

    private function cobertura(array $objetivo, Collection $declaradas): string
    {
        $rangos = [];
        $incompleta = false;
        foreach ($declaradas as $declarada) {
            $rango = $this->rango($declarada);
            if (! $rango) {
                $incompleta = true;
                continue;
            }
            $coincide = $rango[0] < $objetivo[1] && $objetivo[0] < $rango[1];
            $tipo = mb_strtolower(trim((string) $declarada->tipo_disponibilidad));
            if ($tipo === 'no disponible' && $coincide) return 'fuera';
            if ($tipo === 'disponible') $rangos[] = $rango;
            elseif ($tipo !== 'no disponible' && $coincide) $incompleta = true;
        }
        if ($incompleta) return 'datos';

        usort($rangos, fn ($a, $b) => $a[0] <=> $b[0]);
        $cursor = $objetivo[0];
        foreach ($rangos as [$inicio, $fin]) {
            if ($inicio <= $cursor && $fin > $cursor) $cursor = $fin;
            if ($cursor >= $objetivo[1]) return 'cubierto';
        }

        return 'fuera';
    }

    private function rango($registro): ?array
    {
        $inicio = $this->segundos($registro->bloqueHorario?->hora_inicio);
        $fin = $this->segundos($registro->bloqueHorario?->hora_fin);

        return $inicio !== null && $fin !== null && $inicio < $fin ? [$inicio, $fin] : null;
    }

    private function segundos(?string $hora): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/D', trim($hora ?? ''), $partes)) return null;
        [$h, $m, $s] = [(int) $partes[1], (int) $partes[2], (int) ($partes[3] ?? 0)];

        return $h < 24 && $m < 60 && $s < 60 ? $h * 3600 + $m * 60 + $s : null;
    }
}
