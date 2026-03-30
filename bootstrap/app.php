<?php

use App\Http\Middleware\CheckModulo;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\VerificarConsentimientoDatos;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'       => CheckRole::class,
            'permission' => CheckPermission::class,
            'modulo'     => CheckModulo::class,
            'consentimiento' => VerificarConsentimientoDatos::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 419 Page Expired (CSRF token expirado) → redirigir al login
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            return redirect()->route('login')
                ->with('error', 'Tu sesión expiró. Por favor inicia sesión nuevamente.');
        });
    })->create();
