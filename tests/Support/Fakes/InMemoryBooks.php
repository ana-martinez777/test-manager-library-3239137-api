<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Errors\IsbnTaken;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Book;

/**
 * Guarda copias (no referencias) para comportarse como una base real: lo que el caso de uso
 * cambia en memoria no se «guarda» hasta que llama al repositorio.
 */
final class InMemoryBooks implements BookRepository
{
    /** @var array<string, Book> */
    private array $rows = [];

    /** Simula que otro proceso agota los ejemplares de un libro antes del próximo guardado. */
    public ?string $stealLastCopyOnNextLoan = null;

    public function put(Book $book): void
    {
        $this->rows[$book->id] = clone $book;
    }

    public function findActive(string $id): ?Book
    {
        $book = $this->rows[$id] ?? null;

        return $book === null || $book->isDeleted() ? null : clone $book;
    }

    public function findAny(string $id): ?Book
    {
        return isset($this->rows[$id]) ? clone $this->rows[$id] : null;
    }

    public function findActiveByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if ($book = $this->findActive($id)) {
                $found[$id] = $book;
            }
        }

        return $found;
    }

    public function existsActiveIsbn(string $isbn): bool
    {
        foreach ($this->rows as $book) {
            if (!$book->isDeleted() && $book->isbn()->value === $isbn) {
                return true;
            }
        }

        return false;
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $found = array_values(array_filter($this->rows, static fn (Book $b) => !$b->isDeleted()
            && ($text === null || stripos($b->title(), $text) !== false || stripos($b->author(), $text) !== false)
            && ($categoryId === null || $b->categoryId() === $categoryId)));
        usort($found, static fn (Book $a, Book $b) => strcmp($a->title(), $b->title()));

        return new Page(array_map(fn (Book $b) => clone $b, array_slice($found, ($page - 1) * $size, $size)), $page, $size, count($found));
    }

    public function add(Book $book): void
    {
        if ($this->existsActiveIsbn($book->isbn()->value)) {
            throw new IsbnTaken();
        }
        $this->put($book);
    }

    public function update(Book $book, int $copiesDifference): void
    {
        $this->put($book);
    }

    public function markDeleted(Book $book): void
    {
        $this->put($book);
    }

    public function updateCover(Book $book): void
    {
        $this->put($book);
    }

    /** Lo usa InMemoryLoans para aplicar el descuento como lo haría el motor. */
    public function applyWithdraw(string $bookId): bool
    {
        if ($this->stealLastCopyOnNextLoan === $bookId) {
            $this->stealLastCopyOnNextLoan = null;
            while ($this->rows[$bookId]->availableCopies() > 0) {
                $this->rows[$bookId]->withdraw();
            }

            return false;
        }
        $book = $this->rows[$bookId] ?? null;
        if ($book === null || $book->isDeleted() || $book->availableCopies() < 1) {
            return false;
        }
        $book->withdraw();

        return true;
    }

    public function applyRestock(string $bookId): void
    {
        $this->rows[$bookId]->restock();
    }

    /** @param list<string> $ids */
    public function snapshot(array $ids): array
    {
        return array_map(fn (string $id) => clone $this->rows[$id], $ids);
    }

    public function restore(array $books): void
    {
        foreach ($books as $book) {
            $this->rows[$book->id] = $book;
        }
    }
}
