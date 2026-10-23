<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Ports\PasswordHasher;

final class PlainHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:' . strrev($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === $this->hash($plainPassword);
    }
}
