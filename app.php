<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AuditMutations;
use App\Http\Middleware\EnsureApplicationRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureApplicationRole::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(AuditMutations::class);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('minha-licenca*') => route('licenca-cliente.login'),
            $request->is('portal*') => route('cliente.login'),
            default => route('rental.login'),
        });

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'locx/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
