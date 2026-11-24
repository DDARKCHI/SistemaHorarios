<?php

namespace App\Notifications;

use App\Services\IdentidadVisual;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordCambiadaNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $identidad = app(IdentidadVisual::class);

        return (new MailMessage)
            ->from(config('mail.from.address'), $identidad->remitenteCorreo())
            ->subject('Contraseña actualizada | '.$identidad->datos()['nombre'])
            ->view('emails.password-cambiada', [
                'usuario' => $notifiable,
            ]);
    }
}
