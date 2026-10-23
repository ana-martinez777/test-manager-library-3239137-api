<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\PasswordHasher;

/** Plan D-09: BCrypt factor 12. La clave en claro nunca se guarda ni se registra. */
final class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly int $cost = 12)
    {
    }

    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
