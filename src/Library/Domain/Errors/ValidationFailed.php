<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class ValidationFailed extends DomainError
{
    public function __construct(string $message = 'Los datos enviados no son válidos.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }
}
