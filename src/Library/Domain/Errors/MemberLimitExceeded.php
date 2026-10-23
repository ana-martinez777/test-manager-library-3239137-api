<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

final class MemberLimitExceeded extends DomainError
{
    public function __construct(string $message = 'El socio superaría el máximo de 3 libros sin devolver.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'MEMBER_LIMIT_EXCEEDED';
    }
}
