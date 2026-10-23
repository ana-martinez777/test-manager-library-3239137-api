<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Ports\TokenIssuer;
use Library\Application\ReadModels\IssuedToken;

final class FakeTokens implements TokenIssuer
{
    public function issue(string $userId, string $role): IssuedToken
    {
        return new IssuedToken("token-{$userId}-{$role}", 28800);
    }
}
