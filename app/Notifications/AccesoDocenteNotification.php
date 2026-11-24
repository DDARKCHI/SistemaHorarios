<?php

namespace App\Notifications;

use App\Models\PeriodoAcademico;
use App\Models\User;
use App\Services\IdentidadVisual;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccesoDocenteNotification extends Notification
{
    public function __construct(
        private User $usuario,
        private PeriodoAcademico $periodo,
        private ?string $claveTemporal,
        private string $fechaLimite,
        private string $rutAcceso
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $marca = app(IdentidadVisual::class);
        $identidad = $marca->datos();

        return (new MailMessage)
            ->from(config('mail.from.address'), $marca->remitenteCorreo())
            ->subject('Acceso y disponibilidad docente | '.$identidad['nombre'])
            ->view('emails.acceso-docente', [
                'usuario' => $this->usuario,
                'periodo' => $this->periodo,
                'claveTemporal' => $this->claveTemporal,
                'fechaLimite' => $this->fechaLimite,
                'rutAcceso' => $this->rutAcceso,
                'url' => route('login'),
            ]);
    }
}
