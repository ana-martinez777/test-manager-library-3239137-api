<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class MemberHasOverdue extends DomainError
{
    public function __construct(string $message = 'El socio tiene libros vencidos y no puede pedir más hasta devolverlos.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'MEMBER_HAS_OVERDUE';
    }
}
