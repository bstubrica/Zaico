<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsa a los usuarios autenticados cuya cuenta fue deshabilitada. Sin esto,
 * deshabilitar a un usuario con sesión activa no surtiría efecto hasta que
 * cerrara sesión.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->estaActivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'La cuenta está deshabilitada.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Su cuenta está deshabilitada. Contacte a un administrador.',
            ]);
        }

        return $next($request);
    }
}
