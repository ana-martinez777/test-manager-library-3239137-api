<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;
use Library\Application\ReadModels\StoredCover;

/** GET /books/{id}/cover: devuelve el binario o 404 (api-contract.md §3.4). */
final class GetCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $storage)
    {
    }

    public function get(string $bookId): StoredCover
    {
        $book = $this->books->findActive($bookId) ?? throw new NotFound('El libro no existe.');
        $key = $book->coverKey() ?? throw new NotFound('El libro no tiene portada.');

        return $this->storage->get($key) ?? throw new NotFound('El libro no tiene portada.');
    }
}
