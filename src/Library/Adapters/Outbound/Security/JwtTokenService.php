<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\Clock;
use Library\Application\Ports\TokenIssuer;
use Library\Application\Ports\TokenVerifier;
use Library\Application\ReadModels\IssuedToken;
use Library\Application\ReadModels\TokenClaims;

/**
 * Plan D-11 y api-contract.md §4: JWT HS256 de 8 horas con `sub` y `role`, sin datos personales.
 * La clave viene del entorno (JWT_SECRET), nunca del repositorio (artículo IX).
 */
final class JwtTokenService implements TokenIssuer, TokenVerifier
{
    public const TTL_SECONDS = 8 * 3600;

    public function __construct(
        #[\SensitiveParameter] private readonly string $secret,
        private readonly Clock $clock,
        private readonly int $ttlSeconds = self::TTL_SECONDS,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET debe tener al menos 32 caracteres.');
        }
    }

    public function issue(string $userId, string $role): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $header = self::encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = self::encode(['sub' => $userId, 'role' => $role, 'iat' => $now, 'exp' => $now + $this->ttlSeconds]);
        $signature = self::base64Url(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));

        return new IssuedToken("{$header}.{$payload}.{$signature}", $this->ttlSeconds);
    }

    public function verify(string $token): ?TokenClaims
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;

        $expected = self::base64Url(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $headerData = self::decode($header);
        $claims = self::decode($payload);
        if (($headerData['alg'] ?? null) !== 'HS256' || $claims === null) {
            return null;
        }
        if (!is_string($claims['sub'] ?? null) || !is_string($claims['role'] ?? null) || !is_int($claims['exp'] ?? null)) {
            return null;
        }
        if ($claims['exp'] <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        return new TokenClaims($claims['sub'], $claims['role']);
    }

    private static function encode(array $data): string
    {
        return self::base64Url(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function decode(string $part): ?array
    {
        $json = base64_decode(strtr($part, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
