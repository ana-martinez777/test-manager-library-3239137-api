<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\IsbnTaken;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryRepository;
use Library\Application\Ports\IdGenerator;
use Library\Application\ReadModels\BookView;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/** HU-03 · CA-03.1 y CA-03.2. */
final class CreateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly IdGenerator $ids,
    ) {
    }

    public function create(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): BookView
    {
        $category = $this->categories->find($categoryId);
        if ($category === null) {
            throw new ValidationFailed('La categoría no existe.', [['field' => 'category_id', 'issue' => 'categoría inexistente']]);
        }

        $book = Book::create($this->ids->newId(), $title, $author, Isbn::fromString($isbn), $categoryId, Quantity::of($totalCopies));

        if ($this->books->existsActiveIsbn($book->isbn()->value)) {
            throw new IsbnTaken();
        }
        $this->books->add($book);

        return new BookView($book, $category);
    }
}
