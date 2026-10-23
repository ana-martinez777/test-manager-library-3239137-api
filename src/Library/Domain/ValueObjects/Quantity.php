<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Errors\ValidationFailed;

/** Cantidad de ejemplares: entero mayor o igual a 1 (CA-03.3). */
final class Quantity
{
    private function __construct(public readonly int $value)
    {
    }

    public static function of(int $value, string $field = 'total_copies'): self
    {
        if ($value < 1) {
            throw new ValidationFailed(
                'El total de ejemplares debe ser al menos 1.',
                [['field' => $field, 'issue' => 'debe ser mayor o igual a 1']]
            );
        }

        return new self($value);
    }
}
