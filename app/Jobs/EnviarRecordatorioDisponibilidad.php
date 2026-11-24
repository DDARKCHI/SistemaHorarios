<?php

namespace App\Jobs;

use App\Models\RecordatorioDisponibilidad;
use App\Services\RecordatoriosDisponibilidad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnviarRecordatorioDisponibilidad implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(private int $recordatorioId) {}

    public function handle(RecordatoriosDisponibilidad $recordatorios): void
    {
        try {
            $recordatorios->enviar($this->recordatorioId);
        } catch (Throwable $error) {
            $this->failed($error);
        }
    }

    public function failed(Throwable $error): void
    {
        // El transporte puede incluir datos del mensaje en sus excepciones.
        RecordatorioDisponibilidad::whereKey($this->recordatorioId)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->update(['estado' => 'fallido', 'error' => 'No se pudo confirmar el envío del recordatorio.']);
    }
}
