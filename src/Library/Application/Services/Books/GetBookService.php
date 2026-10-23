<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\ReadModels\BookView;

/** CA-04.3: inexistente o dado de baja → 404. */
final class GetBookService
{
    public function __construct(private readonly BookRepository $books, private readonly BookViews $views)
    {
    }

    public function get(string $id): BookView
    {
        $book = $this->books->findActive($id) ?? throw new NotFound('El libro no existe.');

        return $this->views->of($book);
    }
}
