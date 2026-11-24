<?php

namespace App\Notifications;

use App\Services\IdentidadVisual;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordPersonalizado extends Notification
{
    use Queueable;

    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $identidad = app(IdentidadVisual::class);
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));

        $minutosExpiracion = config(
            'auth.passwords.' . config('auth.defaults.passwords') . '.expire'
        );

        return (new MailMessage)
            ->from(config('mail.from.address'), $identidad->remitenteCorreo())
            ->subject('Recuperación de contraseña | '.$identidad->datos()['nombre'])
            ->view('emails.recuperar-contrasena', [
                'url' => $url,
                'usuario' => $notifiable,
                'minutosExpiracion' => $minutosExpiracion,
            ]);
    }
}
