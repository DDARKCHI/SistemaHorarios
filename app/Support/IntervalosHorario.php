<?php

namespace App\Support;

use App\Models\BloqueHorario;
use Illuminate\Support\Collection;

class IntervalosHorario
{
    public static function deBloque(?BloqueHorario $bloque): ?array
    {
        $inicio = self::segundos($bloque?->hora_inicio);
        $fin = self::segundos($bloque?->hora_fin);
        return $inicio !== null && $fin !== null && $inicio < $fin ? [$inicio, $fin] : null;
    }

    public static function seSuperponen(array $a, array $b): bool
    {
        return $a[0] < $b[1] && $b[0] < $a[1];
    }

    public static function coberturaDisponibilidad(array $objetivo, Collection $declaradas): string
    {
        $rangos = [];
        $incompleta = false;
        foreach ($declaradas as $declarada) {
            $rango = self::deBloque($declarada->bloqueHorario);
            if (! $rango) { $incompleta = true; continue; }
            $coincide = self::seSuperponen($rango, $objetivo);
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

    private static function segundos(?string $hora): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/D', trim($hora ?? ''), $partes)) return null;
        [$h, $m, $s] = [(int) $partes[1], (int) $partes[2], (int) ($partes[3] ?? 0)];
        return $h < 24 && $m < 60 && $s < 60 ? $h * 3600 + $m * 60 + $s : null;
    }
}
