<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CuentasDocentes;
use App\Support\PermisosSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function mostrarLogin()
    {
        return view('login');
    }

    public function iniciarSesion(Request $request)
    {
        $credenciales = $request->validate([
            'rut' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
        ]);

        $cuenta = CuentasDocentes::buscarUsuario($credenciales['rut']);
        $credenciales['rut'] = $cuenta?->rut ?? trim($credenciales['rut']);
        if (Auth::attempt($credenciales)) {
            $request->session()->regenerate();

            $usuario = Auth::user();

            $entrada = PermisosSistema::entrada($usuario);
            if ($entrada) {
                $ahora = now();
                $primerIngreso = User::whereKey($usuario->id)->whereNull('primer_ingreso_en')
                    ->update(['primer_ingreso_en' => $ahora, 'ultimo_ingreso_en' => $ahora]);
                if (! $primerIngreso) {
                    User::whereKey($usuario->id)->update(['ultimo_ingreso_en' => $ahora]);
                }

                return redirect()->route($usuario->debe_cambiar_password ? 'cuenta.password.edit' : $entrada);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['rut' => 'Tu usuario no tiene permisos para acceder al sistema. Solicita la revisión de tu perfil.']);
        }

        return back()->withErrors([
            'rut' => 'El usuario o la contraseña son incorrectos.',
        ])->onlyInput('rut');
    }

    public function cerrarSesion(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
