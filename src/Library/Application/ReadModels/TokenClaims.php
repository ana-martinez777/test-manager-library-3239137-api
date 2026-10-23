<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

final class TokenClaims
{
    public function __construct(public readonly string $userId, public readonly string $role)
    {
    }
}
