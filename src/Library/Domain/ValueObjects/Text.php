<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Errors\ValidationFailed;

/** Normaliza y valida textos obligatorios del dominio (recortados, no vacíos, con longitud máxima). */
final class Text
{
    public static function required(string $value, string $field, int $maxLength): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new ValidationFailed("El campo {$field} es obligatorio.", [['field' => $field, 'issue' => 'es obligatorio']]);
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new ValidationFailed(
                "El campo {$field} admite como máximo {$maxLength} caracteres.",
                [['field' => $field, 'issue' => "máximo {$maxLength} caracteres"]]
            );
        }

        return $trimmed;
    }
}
