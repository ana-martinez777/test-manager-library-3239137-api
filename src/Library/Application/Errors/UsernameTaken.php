<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class UsernameTaken extends DomainError
{
    public function __construct(string $message = 'Ese nombre de usuario ya está registrado.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'USERNAME_TAKEN';
    }
}
