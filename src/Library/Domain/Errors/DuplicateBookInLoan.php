<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class DuplicateBookInLoan extends DomainError
{
    public function __construct(string $message = 'Un préstamo no puede repetir el mismo libro.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_BOOK_IN_LOAN';
    }
}
