<?php

namespace App\Support;

class ProgresoModulos
{
    public static function calcular(int $asignados, int $requeridos): array
    {
        $estado = match (true) {
            $requeridos <= 0 => 'sin_carga',
            $asignados > $requeridos => 'excedido',
            $asignados === $requeridos => 'completo',
            $asignados > 0 => 'pendiente',
            default => 'sin_asignar',
        };

        return [
            'estado' => $estado,
            'asignados' => $asignados,
            'requeridos' => $requeridos,
            'porcentaje' => $requeridos > 0 ? round(($asignados / $requeridos) * 100, 1) : null,
            'faltantes' => $requeridos > 0 ? max(0, $requeridos - $asignados) : 0,
            'excedentes' => $requeridos > 0 ? max(0, $asignados - $requeridos) : 0,
            'puede_agregar' => $requeridos > 0 && $asignados < $requeridos,
        ];
    }
}
