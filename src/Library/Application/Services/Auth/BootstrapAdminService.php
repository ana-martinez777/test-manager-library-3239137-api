<?php

declare(strict_types=1);

namespace Library\Application\Services\Auth;

use Library\Application\Ports\IdGenerator;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\UserRepository;
use Library\Domain\Model\User;

/**
 * Plan D-10 y data-model.md §9: el admin inicial lo crea el arranque con credenciales de entorno.
 * Es idempotente: si el usuario ya existe, no hace nada.
 */
final class BootstrapAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    /** @return bool true si lo creó, false si ya existía */
    public function ensureAdmin(string $username, string $password): bool
    {
        if (trim($username) === '' || $password === '') {
            throw new \InvalidArgumentException('ADMIN_USERNAME y ADMIN_PASSWORD son obligatorios.');
        }
        if ($this->users->findByUsername(User::normalizeUsername($username)) !== null) {
            return false;
        }
        $this->users->add(User::createAdmin($this->ids->newId(), $username, $this->hasher->hash($password)));

        return true;
    }
}
