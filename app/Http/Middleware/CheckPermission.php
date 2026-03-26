<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Verifica que el rol del usuario tenga el permiso indicado.
     *
     * Uso: middleware('permission:puede_aprobar')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->rol) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        if (!$user->rol->{$permission}) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
