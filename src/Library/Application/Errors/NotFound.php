<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class NotFound extends DomainError
{
    public function __construct(string $message = 'El recurso solicitado no existe.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }
}
