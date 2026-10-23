<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

final class IssuedToken
{
    public function __construct(public readonly string $accessToken, public readonly int $expiresIn)
    {
    }
}
