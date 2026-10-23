<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

use Library\Domain\Errors\ValidationFailed;

/** Paginación del contrato: page >= 1, 1 <= size <= 50 (api-contract.md §1). */
final class PageRequest
{
    public const MAX_SIZE = 50;

    private function __construct(public readonly int $page, public readonly int $size)
    {
    }

    public static function of(int $page = 1, int $size = 20): self
    {
        $details = [];
        if ($page < 1) {
            $details[] = ['field' => 'page', 'issue' => 'debe ser mayor o igual a 1'];
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            $details[] = ['field' => 'size', 'issue' => 'debe estar entre 1 y ' . self::MAX_SIZE];
        }
        if ($details !== []) {
            throw new ValidationFailed('Los parámetros de paginación no son válidos.', $details);
        }

        return new self($page, $size);
    }
}
