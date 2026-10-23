<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\IssuedToken;

interface TokenIssuer
{
    public function issue(string $userId, string $role): IssuedToken;
}
