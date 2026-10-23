<?php

declare(strict_types=1);

namespace Library\Application\Errors;

use Library\Domain\Errors\DomainError;

final class DocumentTaken extends DomainError
{
    public function __construct(string $message = 'Ya existe un socio con ese número de documento.', array $details = [])
    {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'DOCUMENT_TAKEN';
    }
}
