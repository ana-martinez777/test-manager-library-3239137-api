<?php

declare(strict_types=1);

namespace Library\Domain\Model;

use DateTimeImmutable;
use Library\Domain\Errors\DuplicateBookInLoan;
use Library\Domain\Errors\ValidationFailed;

/**
 * Agregado Préstamo (architecture.md §3).
 * 1 a 3 líneas, sin libro repetido, vencimiento a 14 días, inmutable salvo la devolución.
 * El estado (open, overdue, closed) se calcula, no se almacena (artículo VII).
 */
final class Loan
{
    public const LOAN_DAYS = 14;
    public const MAX_ITEMS = 3;
    public const MAX_UNRETURNED_PER_MEMBER = 3;

    public const STATUS_OPEN = 'open';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CLOSED = 'closed';

    /** @var list<LoanItem> */
    private array $items = [];

    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
    ) {
    }

    public static function open(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt);
    }

    /** @param list<LoanItem> $items */
    public static function rehydrate(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $items): self
    {
        $loan = new self($id, $memberId, $loanedByUserId, $loanedAt);
        $loan->items = array_values($items);

        return $loan;
    }

    /**
     * Añade una línea: descuenta el ejemplar (Book::withdraw) y congela los datos del libro
     * en la misma operación (architecture.md §3).
     */
    public function addItem(string $itemId, Book $book, string $categoryName): LoanItem
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new ValidationFailed(
                'Un préstamo admite de 1 a 3 libros.',
                [['field' => 'items', 'issue' => 'máximo 3 libros']]
            );
        }
        foreach ($this->items as $existing) {
            if ($existing->bookId === $book->id) {
                throw new DuplicateBookInLoan("El libro «{$book->title()}» está repetido en el préstamo.");
            }
        }

        $book->withdraw();

        $item = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn()->value,
            $categoryName,
            $this->loanedAt->modify('+' . self::LOAN_DAYS . ' days'),
        );
        $this->items[] = $item;

        return $item;
    }

    /** CA-07.1: devuelve una línea y suma un ejemplar al libro, una sola vez. */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $at): LoanItem
    {
        $item = $this->item($itemId);
        if ($item === null) {
            throw new \InvalidArgumentException('La línea no pertenece a este préstamo.');
        }
        if ($item->bookId !== $book->id) {
            throw new \InvalidArgumentException('El libro no corresponde a la línea.');
        }

        $item->markReturned($at);
        $book->restock();

        return $item;
    }

    public function item(string $itemId): ?LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** CA-08.1 y api-contract.md §3.6. */
    public function status(DateTimeImmutable $now): string
    {
        $pending = array_filter($this->items, static fn (LoanItem $i) => !$i->isReturned());
        if ($pending === []) {
            return self::STATUS_CLOSED;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return self::STATUS_OVERDUE;
            }
        }

        return self::STATUS_OPEN;
    }
}
