<?php

namespace App\Services;

use App\Models\Horario;
use App\Support\IntervalosHorario;
use Illuminate\Support\Collection;

class RevisionDisponibilidadProgramacion
{
    public function __construct(private Collection $disponibilidades, private Collection $docentesVersion) {}

    public function revisar(Horario $horario, Collection $docentes): ?array
    {
        $objetivo = IntervalosHorario::deBloque($horario->bloqueHorario);
        if (! $objetivo || ! $horario->dia) return null;

        $mensajes = $recursos = [];
        foreach ($docentes->unique('profesor_id') as $asignacion) {
            $declaradas = $this->disponibilidades->get($asignacion->profesor_id, collect());
            $resultado = $declaradas->isEmpty() ? 'sin_declaracion'
                : IntervalosHorario::coberturaDisponibilidad($objetivo, $declaradas->where('dia_id', $horario->dia_id));
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

}
