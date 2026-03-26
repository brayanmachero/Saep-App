<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verifica que el usuario tenga uno de los roles permitidos.
     *
     * Uso: middleware('role:SUPER_ADMIN,PREVENCIONISTA')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->rol) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        if (!in_array($user->rol->codigo, $roles, true)) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
