<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

use Library\Domain\Model\Book;
use Library\Domain\Model\Category;

/** Libro con su categoría resuelta, para responder (api-contract.md §3.4). */
final class BookView
{
    public function __construct(public readonly Book $book, public readonly Category $category)
    {
    }
}
