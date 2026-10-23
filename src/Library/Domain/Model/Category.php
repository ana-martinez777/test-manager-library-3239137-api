<?php

declare(strict_types=1);

namespace Library\Domain\Model;

/** Categoría fija, sembrada por la migración (data-model.md §9). Solo lectura. */
final class Category
{
    public function __construct(public readonly string $id, public readonly string $name)
    {
    }
}
