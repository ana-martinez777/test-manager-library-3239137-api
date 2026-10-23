<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class BookNotAvailable extends DomainError
{
    public function __construct(string $message = 'No quedan ejemplares disponibles.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'BOOK_NOT_AVAILABLE';
    }
}
