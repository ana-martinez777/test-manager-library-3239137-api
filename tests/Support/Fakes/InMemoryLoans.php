<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use DateTimeImmutable;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\Ports\LoanRepository;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\ReadModels\OverdueLine;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Loan;
use Library\Domain\Model\LoanItem;

final class InMemoryLoans implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

    public function __construct(private readonly InMemoryBooks $books, private readonly InMemoryMembers $members)
    {
    }

    public function find(string $id): ?Loan
    {
        return isset($this->loans[$id]) ? $this->copy($this->loans[$id]) : null;
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        return count($this->pendingItems($memberId));
    }

    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        foreach ($this->pendingItems($memberId) as $item) {
            if ($item->isOverdue($now)) {
                return true;
            }
        }

        return false;
    }

    public function bookHasOpenLoans(string $bookId): bool
    {
        foreach ($this->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->bookId === $bookId && !$item->isReturned()) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Transacción simulada: si un descuento falla, se restauran los libros y no se guarda nada. */
    public function add(Loan $loan, array $books): void
    {
        $ids = array_map(static fn (LoanItem $i) => $i->bookId, $loan->items());
        $before = $this->books->snapshot($ids);
        foreach ($ids as $id) {
            if (!$this->books->applyWithdraw($id)) {
                $this->books->restore(array_filter($before, static fn ($b) => $b->id !== $id));
                throw new ConcurrencyConflict('sin ejemplares');
            }
        }
        $this->loans[$loan->id] = $this->copy($loan);
    }

    public function saveReturn(Loan $loan, string $itemId): void
    {
        $stored = $this->loans[$loan->id];
        if ($stored->item($itemId)->isReturned()) {
            throw new ConcurrencyConflict('ya devuelta');
        }
        $this->loans[$loan->id] = $this->copy($loan);
        $this->books->applyRestock($loan->item($itemId)->bookId);
    }

    public function search(LoanSearchCriteria $criteria, DateTimeImmutable $now, int $page, int $size): Page
    {
        $found = array_values(array_filter($this->loans, static fn (Loan $l) => ($criteria->memberId === null || $l->memberId === $criteria->memberId)
            && ($criteria->status === null || $l->status($now) === $criteria->status)
            && ($criteria->fromInclusive === null || $l->loanedAt >= $criteria->fromInclusive)
            && ($criteria->toExclusive === null || $l->loanedAt < $criteria->toExclusive)));
        usort($found, static fn (Loan $a, Loan $b) => $b->loanedAt <=> $a->loanedAt);

        return new Page(array_slice($found, ($page - 1) * $size, $size), $page, $size, count($found));
    }

    public function overdue(DateTimeImmutable $now): array
    {
        $lines = [];
        foreach ($this->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $lines[] = new OverdueLine($loan->id, $item->id, $item->bookTitle, $this->members->find($loan->memberId)->fullName, $item->dueAt, LoanItem::daysLate($item->dueAt, $now));
                }
            }
        }

        return $lines;
    }

    /** @return list<LoanItem> */
    private function pendingItems(string $memberId): array
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($loan->memberId === $memberId) {
                foreach ($loan->items() as $item) {
                    if (!$item->isReturned()) {
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }

    private function copy(Loan $loan): Loan
    {
        return Loan::rehydrate($loan->id, $loan->memberId, $loan->loanedByUserId, $loan->loanedAt, array_map(static fn (LoanItem $i) => clone $i, $loan->items()));
    }
}
