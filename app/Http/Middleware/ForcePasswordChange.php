<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->must_change_password) {
            $allowed = ['perfil.show', 'perfil.password', 'logout'];

            if (!in_array($request->route()?->getName(), $allowed)) {
                return redirect()->route('perfil.show')
                    ->with('warning', 'Debe cambiar su contraseña provisoria antes de continuar.');
            }
        }

        return $next($request);
    }
}
