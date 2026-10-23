<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class IsbnTaken extends DomainError
{
    public function __construct(string $message = 'Ya existe un libro activo con ese ISBN.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'ISBN_TAKEN';
    }
}
