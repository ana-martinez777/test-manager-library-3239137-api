<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\ErrorRenderer;
use Library\Application\Ports\TokenVerifier;
use Symfony\Component\HttpFoundation\Response;

/** CA-01.3: todo endpoint salvo login y /health exige token. Sin token o inválido → 401. */
final class AuthenticateToken
{
    public function __construct(private readonly TokenVerifier $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token ? $this->tokens->verify($token) : null;
        if ($claims === null) {
            return ErrorRenderer::json(401, 'INVALID_CREDENTIALS', 'Se requiere un token de acceso válido.');
        }

        $request->attributes->set('auth.user_id', $claims->userId);
        $request->attributes->set('auth.role', $claims->role);

        return $next($request);
    }
}
