<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\ErrorRenderer;
use Library\Adapters\Inbound\Http\Middleware\AuthenticateToken;
use Library\Adapters\Inbound\Http\Middleware\RequireRole;
use Library\Domain\Errors\DomainError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores de negocio son respuestas esperadas, no fallos: no se registran en el log.
        $exceptions->dontReport([DomainError::class]);

        // Cuerpo de error único para toda la API (api-contract.md §1, RNF-4).
        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*') ? ErrorRenderer::render($e) : null;
        });
    })->create();
