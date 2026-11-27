<?php

namespace App\Support;

use App\Models\User;

final class PanelCarrera
{
    public const PESTANAS = ['horario', 'ramos', 'laboratorios', 'docentes', 'borradores'];

    public static function visible(?User $usuario): bool
    {
        return $usuario
            && $usuario->hasAnyRole(['Administrador', 'Jefe de carrera'])
            && $usuario->can('ver programacion semestral');
    }
}
