<?php

namespace App\Jobs;

use App\Models\EnvioAccesoDocente;
use App\Models\PeriodoAcademico;
use App\Services\EnviosAccesosDocentes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarAccesoDocente implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;
    public int $uniqueFor = 3600;

    public function __construct(
        private int $periodoId,
        private int $profesorId,
        private int $actor,
        private ?string $ip,
        private string $lote,
        private string $fechaLimite
    ) {}

    public function uniqueId(): string
    {
        return $this->lote.':'.$this->profesorId;
    }

    public function handle(EnviosAccesosDocentes $envios): void
    {
        $envios->enviar(
            PeriodoAcademico::findOrFail($this->periodoId), [$this->profesorId], $this->actor,
            $this->ip, $this->lote, $this->fechaLimite
        );
    }

    public function failed(\Throwable $error): void
    {
        EnvioAccesoDocente::where('lote', $this->lote)->where('profesor_id', $this->profesorId)
            ->whereIn('estado', ['pendiente', 'en_proceso'])->update([
                'estado' => 'fallido',
                'error' => 'El envío no pudo procesarse. Revisa el acceso del responsable y vuelve a intentarlo.',
            ]);
    }
}
