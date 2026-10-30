<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DiasCalendario
{
    public static function conClases(Collection $dias, Collection $horarios): Collection
    {
        return $dias->filter(fn ($dia) => (int) $dia->numero !== 6
            || $horarios->contains('dia_id', $dia->id))->values();
    }
}
