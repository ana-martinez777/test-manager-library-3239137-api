<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\Model\Category;

interface CategoryRepository
{
    /** @return list<Category> ordenadas por nombre */
    public function all(): array;

    public function find(string $id): ?Category;
}
