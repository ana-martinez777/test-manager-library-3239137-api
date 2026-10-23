<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\Model\User;

interface UserRepository
{
    /** Q10. Recibe el nombre ya normalizado. */
    public function findByUsername(string $username): ?User;

    /** Lanza UsernameTaken si choca con el índice único. */
    public function add(User $user): void;
}
