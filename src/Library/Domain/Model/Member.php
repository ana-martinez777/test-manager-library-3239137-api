<?php

declare(strict_types=1);

namespace Library\Domain\Model;

use Library\Domain\ValueObjects\DocumentNumber;
use Library\Domain\ValueObjects\Text;

/** Socio: persona que pide libros. No inicia sesión (spec.md §1). No se elimina (CA-05.3). */
final class Member
{
    private function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly DocumentNumber $documentNumber,
    ) {
    }

    public static function create(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        return new self($id, Text::required($fullName, 'full_name', 200), $documentNumber);
    }

    public static function rehydrate(string $id, string $fullName, string $documentNumber): self
    {
        return new self($id, $fullName, DocumentNumber::fromString($documentNumber));
    }
}
