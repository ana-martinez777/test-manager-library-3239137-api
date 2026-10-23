<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class InvalidDateRange extends DomainError
{
    public function __construct(string $message = 'La fecha final no puede ser anterior a la inicial.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'INVALID_DATE_RANGE';
    }
}
