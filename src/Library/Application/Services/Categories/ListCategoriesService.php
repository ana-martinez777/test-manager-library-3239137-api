<?php

declare(strict_types=1);

namespace Library\Application\Services\Categories;

use Library\Application\Ports\CategoryRepository;
use Library\Domain\Model\Category;

/** Q4: las cinco categorías, por nombre (api-contract.md §3.3). */
final class ListCategoriesService
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /** @return list<Category> */
    public function list(): array
    {
        return $this->categories->all();
    }
}
