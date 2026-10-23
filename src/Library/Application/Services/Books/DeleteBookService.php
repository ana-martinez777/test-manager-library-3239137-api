<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\BookHasOpenLoans;
use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\Ports\LoanRepository;

/** HU-03 · CA-03.5: baja lógica; con préstamos abiertos → 409 (ADR-003). */
final class DeleteBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    public function delete(string $id): void
    {
        $book = $this->books->findActive($id) ?? throw new NotFound('El libro no existe.');
        if ($this->loans->bookHasOpenLoans($id)) {
            throw new BookHasOpenLoans();
        }

        $book->delete($this->clock->now());
        try {
            $this->books->markDeleted($book);
        } catch (ConcurrencyConflict) {
            throw new NotFound('El libro no existe.');
        }
    }
}
