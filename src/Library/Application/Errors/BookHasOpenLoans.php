<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class BookHasOpenLoans extends DomainError
{
    public function __construct(string $message = 'El libro tiene préstamos sin devolver y no se puede dar de baja.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'BOOK_HAS_OPEN_LOANS';
    }
}
