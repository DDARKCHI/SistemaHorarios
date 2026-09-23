<?php

namespace App\Http\Controllers;

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
            'rut' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($credenciales)) {
            $request->session()->regenerate();

            $usuario = Auth::user();

            if ($usuario->can('ver panel inicio')) {
                return redirect()->route('inicio');
            }

            return redirect()->route('programacion.semestral');
        }

        return back()->withErrors([
            'rut' => 'El RUT o la contraseña son incorrectos.',
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