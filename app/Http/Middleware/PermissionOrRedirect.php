<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionOrRedirect
{
    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $usuario = Auth::user();

        if ($usuario->can($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);

        $entrada = \App\Support\PermisosSistema::entrada($usuario);
        if ($entrada) return redirect()->route($entrada)->with('error', 'Tu perfil no tiene permiso para acceder a esa función.');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'rut' => 'Tu usuario no tiene permisos para acceder al sistema.',
            ]);
    }
}
