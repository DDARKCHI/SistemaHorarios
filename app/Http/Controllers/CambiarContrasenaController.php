<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CambiarContrasenaController extends Controller
{
    public function edit() { return view('auth.cambiar-contrasena'); }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed', 'different:current_password'],
        ], ['current_password.current_password' => 'La contraseña actual no es correcta.', 'password.different' => 'Elige una contraseña distinta de la actual.']);
        DB::transaction(function () use ($request, $datos) {
            $usuario = \App\Models\User::lockForUpdate()->findOrFail($request->user()->id);
            if (! \Illuminate\Support\Facades\Hash::check($datos['current_password'], $usuario->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'La contraseña cambió. Vuelve a iniciar sesión.']);
            }
            $usuario->password = $datos['password'];
            $usuario->remember_token = Str::random(60);
            $usuario->debe_cambiar_password = false;
            $usuario->clave_temporal = null;
            $usuario->save();
            DB::table('historial_acciones')->insert(['usuario_id' => $usuario->id, 'accion' => 'Cambiar contraseña propia', 'modulo' => 'Mi cuenta', 'tabla_afectada' => 'users', 'registro_id' => $usuario->id, 'descripcion' => 'Contraseña actualizada por su titular.', 'datos' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        });
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return to_route('login')->with('status', 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.');
    }
}
