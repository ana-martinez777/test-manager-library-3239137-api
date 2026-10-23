<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Errors\ValidationFailed;

/** ISBN de 13 dígitos (CA-03.2). Solo dígitos, sin guiones. */
final class Isbn
{
    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $value = trim($raw);
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw new ValidationFailed(
                'El ISBN debe tener exactamente 13 dígitos.',
                [['field' => 'isbn', 'issue' => 'debe tener 13 dígitos numéricos']]
            );
        }

        return new self($value);
    }
}
