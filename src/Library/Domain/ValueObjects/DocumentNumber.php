<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

/** Número de documento del socio: obligatorio, recortado, máximo 30 caracteres (data-model.md §3). */
final class DocumentNumber
{
    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        return new self(Text::required($raw, 'document_number', 30));
    }
}
