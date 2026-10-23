<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;

/** Quita la portada: primero se anula cover_key, después se borra el binario (data-model.md §7). */
final class RemoveCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $storage)
    {
    }

    public function remove(string $bookId): void
    {
        $book = $this->books->findActive($bookId) ?? throw new NotFound('El libro no existe.');
        $previousKey = $book->removeCover();
        if ($previousKey === null) {
            return;
        }
        $this->books->updateCover($book);
        $this->storage->delete($previousKey);
    }
}
