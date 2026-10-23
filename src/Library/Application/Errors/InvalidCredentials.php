<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class InvalidCredentials extends DomainError
{
    public function __construct(string $message = 'Usuario o contraseña incorrectos.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }
}
