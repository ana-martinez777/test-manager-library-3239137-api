<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class ItemAlreadyReturned extends DomainError
{
    public function __construct(string $message = 'Esta línea del préstamo ya fue devuelta.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'ITEM_ALREADY_RETURNED';
    }
}
