<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // Mostrar formulario de inicio de sesión
    public function mostrarLogin()
    {
        return view('login');
    }

    // Procesar inicio de sesión
    public function iniciarSesion(Request $request)
    {
        $credenciales = $request->validate([
            'rut' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($credenciales)) {

            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'rut' => 'El RUT o la contraseña son incorrectos.',
        ])->onlyInput('rut');
    }

    // Cerrar sesión
    public function cerrarSesion(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}