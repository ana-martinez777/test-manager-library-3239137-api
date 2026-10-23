<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Ports\CategoryRepository;
use Library\Application\ReadModels\BookView;
use Library\Domain\Model\Book;
use Library\Domain\Model\Category;

/** Resuelve la categoría de cada libro para la respuesta. */
final class BookViews
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function of(Book $book): BookView
    {
        return new BookView($book, $this->categories->find($book->categoryId()) ?? new Category($book->categoryId(), ''));
    }
}
