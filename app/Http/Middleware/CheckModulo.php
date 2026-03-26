<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulo
{
    /**
     * Verifica que el usuario tenga acceso al módulo indicado.
     * Uso: middleware('modulo:kizeo_analytics') o middleware('modulo:usuarios,puede_editar')
     */
    public function handle(Request $request, Closure $next, string $moduloSlug, string $accion = 'puede_ver'): Response
    {
        $user = $request->user();

        if (!$user || !$user->rol) {
            abort(403, 'Sin permisos para acceder a este módulo.');
        }

        if (!$user->tieneAcceso($moduloSlug, $accion)) {
            abort(403, 'No tienes permisos para acceder a este módulo.');
        }

        return $next($request);
    }
}
