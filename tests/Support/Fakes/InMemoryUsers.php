<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Errors\UsernameTaken;
use Library\Application\Ports\UserRepository;
use Library\Domain\Model\User;

final class InMemoryUsers implements UserRepository
{
    /** @var array<string, User> */
    public array $byUsername = [];

    public function findByUsername(string $username): ?User
    {
        return $this->byUsername[$username] ?? null;
    }

    public function add(User $user): void
    {
        if (isset($this->byUsername[$user->username])) {
            throw new UsernameTaken();
        }
        $this->byUsername[$user->username] = $user;
    }
}
