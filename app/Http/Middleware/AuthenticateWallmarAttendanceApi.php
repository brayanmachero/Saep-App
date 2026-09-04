<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWallmarAttendanceApi
{
    /**
     * La clave de Wallmar es independiente de Talana y se recibe únicamente
     * por header, de modo que no queda expuesta en URLs, historial o logs.
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $configuredKey = config('services.wallmar_attendance.api_key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            return response()->json([
                'message' => 'El servicio de asistencia aún no está habilitado.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedKey = $request->header('X-SAEP-API-Key');

        if (! is_string($providedKey) || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'message' => 'No autorizado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
