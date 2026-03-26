<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarConsentimientoDatos
{
    /**
     * Verifica que el usuario haya aceptado la política de datos personales.
     * Redirige a la vista de consentimiento si no lo ha hecho.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && !auth()->user()->acepta_politica_datos) {
            // Permitir acceso a rutas esenciales sin consentimiento
            $rutasPermitidas = [
                'proteccion-datos.aceptar-politica',
                'proteccion-datos.politica-privacidad',
                'logout',
            ];

            if (!in_array($request->route()?->getName(), $rutasPermitidas)) {
                return redirect()->route('proteccion-datos.consentimiento');
            }
        }

        return $next($request);
    }
}
