<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\ErrorRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización por rol en cada endpoint (api-contract.md §4). Se evalúa ANTES de buscar el
 * recurso, así un 403 no revela si el recurso existe.
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array($request->attributes->get('auth.role'), $roles, true)) {
            return ErrorRenderer::json(403, 'FORBIDDEN_ROLE', 'Tu rol no tiene permiso para esta operación.');
        }

        return $next($request);
    }
}
