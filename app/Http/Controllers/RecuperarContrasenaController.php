<?php

namespace App\Http\Controllers;

use App\Notifications\PasswordCambiadaNotification;
use App\Services\CuentasDocentes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RecuperarContrasenaController extends Controller
{
    public function mostrarSolicitud()
    {
        return view('auth.recuperar-contrasena');
    }

    public function enviarEnlace(Request $request)
    {
        $request->validate([
            'rut' => ['required', 'string', 'max:40'],
        ], [
            'rut.required' => 'Debes ingresar tu RUT o usuario.',
        ]);

        $usuario = CuentasDocentes::buscarUsuario($request->rut);

        $mensaje = 'Si el usuario tiene un correo asociado, recibirás un enlace de recuperación.';

        if (!$usuario || !$usuario->email) {
            return back()->with('status', $mensaje);
        }

        $resultado = Password::sendResetLink([
            'email' => $usuario->email,
        ]);

        if ($resultado !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput()
                ->withErrors([
                    'rut' => 'No fue posible enviar el enlace de recuperación. Inténtalo nuevamente.',
                ]);
        }

        return back()->with('status', $mensaje);
    }

    public function mostrarRestablecimiento(
        Request $request,
        string $token
    ) {
        return view('auth.restablecer-contrasena', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function restablecer(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ], [
            'email.required' => 'No se pudo identificar el correo asociado.',
            'email.email' => 'El correo asociado no es válido.',
            'password.required' => 'Debes ingresar una nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $resultado = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function ($usuario, $password) {
                $usuario->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'debe_cambiar_password' => false,
                    'clave_temporal' => null,
                ])->save();

                $usuario->notify(
                    new PasswordCambiadaNotification()
                );
            }
        );

        if ($resultado !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'El enlace de recuperación no es válido o ha expirado.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.'
            );
    }
}
