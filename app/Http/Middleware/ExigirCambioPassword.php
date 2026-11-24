<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirCambioPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->debe_cambiar_password
            || $request->routeIs('login', 'login.procesar', 'logout', 'cuenta.password.*', 'password.*', 'identidad.*')) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cambia tu contraseña temporal para continuar.',
                'redirect' => route('cuenta.password.edit'),
            ], 423);
        }

        return redirect()->route('cuenta.password.edit');
    }
}
