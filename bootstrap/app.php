<?php

use App\Http\Middleware\CheckModulo;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Http\Middleware\AuthenticateWallmarAttendanceApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'       => CheckRole::class,
            'permission' => CheckPermission::class,
            'modulo'     => CheckModulo::class,
            'consentimiento' => VerificarConsentimientoDatos::class,
            'force.password' => ForcePasswordChange::class,
            'wallmar.attendance' => AuthenticateWallmarAttendanceApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Laravel converts TokenMismatchException to an HTTP 419 before rendering.
        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            // Do not log request bodies, URLs, cookies, identifiers or CSRF tokens.
            Log::notice('session.csrf_expired', [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'json' => $request->expectsJson() || $request->ajax(),
            ]);

            $message = 'La sesión o el formulario venció. Vuelve a ingresar y repite la operación; no se guardaron los cambios de esta solicitud.';

            if ($request->routeIs('epp.respond') && ! $request->expectsJson()) {
                return redirect()->route('epp.portal', ['pedido' => $request->route('pedido')], 303)
                    ->withErrors(['sesion' => 'La sesión venció. Vuelve a revisar y responder la propuesta.'])
                    ->header('Cache-Control', 'no-store');
            }

            if ($request->routeIs('epp.store') && ! $request->expectsJson()) {
                return redirect()->route('epp.portal', [], 303)
                    ->withErrors(['sesion' => 'El formulario venció antes del envío. Vuelve a completar el pedido en este portal.'])
                    ->header('Cache-Control', 'no-store');
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                    'code' => 'SESSION_EXPIRED',
                    'login_url' => route('login'),
                ], 419)->header('Cache-Control', 'no-store');
            }

            // A 303 always follows with GET, never replaying the rejected POST.
            return redirect()->route('login', [], 303)
                ->with('error', $message)
                ->header('Cache-Control', 'no-store');
        });
    })->create();
