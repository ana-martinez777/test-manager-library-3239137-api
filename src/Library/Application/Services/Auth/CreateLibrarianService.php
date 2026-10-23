<?php

declare(strict_types=1);

namespace Library\Application\Services\Auth;

use Library\Application\Errors\UsernameTaken;
use Library\Application\Ports\IdGenerator;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\UserRepository;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\User;

/** HU-02: el admin da de alta bibliotecarios. */
final class CreateLibrarianService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    public function create(string $username, string $password, string $role): User
    {
        if ($password === '') {
            throw new ValidationFailed('La contraseña es obligatoria.', [['field' => 'password', 'issue' => 'es obligatorio']]);
        }

        // Valida rol y nombre antes de calcular el hash (el hash es costoso).
        User::createLibrarian('00000000-0000-4000-8000-000000000000', $username, 'pending', $role);

        if ($this->users->findByUsername(User::normalizeUsername($username)) !== null) {
            throw new UsernameTaken();
        }

        $user = User::createLibrarian($this->ids->newId(), $username, $this->hasher->hash($password), $role);
        $this->users->add($user);

        return $user;
    }
}
