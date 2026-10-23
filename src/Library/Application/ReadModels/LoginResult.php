<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

final class LoginResult
{
    public function __construct(public readonly IssuedToken $token, public readonly string $role)
    {
    }
}
