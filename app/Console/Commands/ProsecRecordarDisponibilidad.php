<?php

namespace App\Console\Commands;

use App\Services\RecordatoriosDisponibilidad;
use Illuminate\Console\Command;

class ProsecRecordarDisponibilidad extends Command
{
    protected $signature = 'prosec:recordar-disponibilidad {--simular : Comprueba los avisos previstos para hoy}';
    protected $description = 'Programa avisos de disponibilidad a los docentes con respuesta pendiente';

    public function handle(RecordatoriosDisponibilidad $recordatorios): int
    {
        $simular = (bool) $this->option('simular');
        $cantidad = $recordatorios->programar($simular);
        $this->info($cantidad.($simular ? ' recordatorio(s) elegible(s). Simulación sin envíos.' : ' recordatorio(s) programado(s).'));

        return self::SUCCESS;
    }
}
