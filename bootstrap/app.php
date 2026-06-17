<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O sistema original não possui token CSRF nos formulários.
        // Esta exceção mantém as telas antigas funcionando com Laravel por trás.
        $middleware->validateCsrfTokens(except: ['locx/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
