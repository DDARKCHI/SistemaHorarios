<?php

namespace App\Notifications;

use App\Models\SolicitudDisponibilidad;
use App\Services\IdentidadVisual;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioDisponibilidadNotification extends Notification
{
    public function __construct(
        private SolicitudDisponibilidad $solicitud,
        private string $nombre,
        private string $rut,
        private bool $claveProvisoria,
        private int $diasAntes
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $marca = app(IdentidadVisual::class);

        return (new MailMessage)
            ->from(config('mail.from.address'), $marca->remitenteCorreo())
            ->subject($this->diasAntes === 1
                ? 'Mañana vence el plazo para registrar tu disponibilidad'
                : 'Recordatorio de disponibilidad docente · '.$marca->datos()['nombre'])
            ->view('emails.recordatorio-disponibilidad', [
                'nombre' => $this->nombre,
                'rut' => $this->rut,
                'claveProvisoria' => $this->claveProvisoria,
                'periodo' => $this->solicitud->periodoAcademico->nombre,
                'fechaLimite' => $this->solicitud->fecha_limite->format('d/m/Y'),
                'diasAntes' => $this->diasAntes,
                'url' => route('mi.disponibilidad.show', $this->solicitud),
            ]);
    }
}
