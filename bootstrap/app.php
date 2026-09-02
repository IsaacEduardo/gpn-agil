<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware de cabeçalhos de segurança HTTP (OWASP)
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        // Webhooks externos e rota de logout são isentos de CSRF para evitar 419 Page Expired
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tratamento suave para expiração de sessão (TokenMismatchException - 419)
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('logout') || $request->routeIs('logout')) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login')->with('info', 'A sua sessão expirou.');
            }

            return redirect()->back()->withInput()->with('warning', 'A sua sessão expirou devido à inatividade. Por favor, tente novamente.');
        });
    })->create();
