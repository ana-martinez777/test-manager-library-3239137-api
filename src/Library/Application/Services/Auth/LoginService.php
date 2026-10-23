<?php

declare(strict_types=1);

namespace Library\Application\Services\Auth;

use Library\Application\Errors\InvalidCredentials;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\TokenIssuer;
use Library\Application\Ports\UserRepository;
use Library\Application\ReadModels\LoginResult;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\User;

/** HU-01: iniciar sesión. */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function login(string $username, string $password): LoginResult
    {
        try {
            $normalized = User::normalizeUsername($username);
        } catch (ValidationFailed) {
            throw new InvalidCredentials();
        }

        $user = $this->users->findByUsername($normalized);
        // CA-01.2: el mismo error para usuario inexistente y clave incorrecta.
        if ($user === null || $password === '' || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials();
        }

        return new LoginResult($this->tokens->issue($user->id, $user->role), $user->role);
    }
}
