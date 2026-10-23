<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryRepository;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\ReadModels\BookView;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\ValueObjects\Quantity;

/** HU-03 · CA-03.3: reemplaza título, autor, categoría y total. */
final class UpdateBookService
{
    public function __construct(private readonly BookRepository $books, private readonly CategoryRepository $categories)
    {
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $totalCopies): BookView
    {
        $book = $this->books->findActive($id) ?? throw new NotFound('El libro no existe.');
        $category = $this->categories->find($categoryId);
        if ($category === null) {
            throw new ValidationFailed('La categoría no existe.', [['field' => 'category_id', 'issue' => 'categoría inexistente']]);
        }

        $difference = $book->update($title, $author, $categoryId, Quantity::of($totalCopies));

        try {
            $this->books->update($book, $difference);
        } catch (ConcurrencyConflict) {
            // Entre la lectura y la escritura se prestó un ejemplar: con el nuevo total quedarían < 0.
            throw new ValidationFailed(
                'El total no puede ser menor que los ejemplares prestados.',
                [['field' => 'total_copies', 'issue' => 'menor que los ejemplares prestados']]
            );
        }

        return new BookView($this->books->findActive($id) ?? $book, $category);
    }
}
