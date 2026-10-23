<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\DuplicateBookInLoan;
use Library\Domain\Errors\ItemAlreadyReturned;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Book;
use Library\Domain\Model\Loan;
use Library\Domain\Model\LoanItem;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/** T-05: Loan con un reloj controlado: 14 días, máximo 3, estado calculado. */
final class LoanTest extends TestCase
{
    private \DateTimeImmutable $loanedAt;

    protected function setUp(): void
    {
        $this->loanedAt = new \DateTimeImmutable('2026-10-14T14:15:00Z');
    }

    private function book(string $id, int $copies = 1): Book
    {
        return Book::create($id, "Libro {$id}", 'Autor', Isbn::fromString('97803074747' . str_pad(substr($id, -2), 2, '0', STR_PAD_LEFT)), 'cat', Quantity::of($copies));
    }

    public function test_item_is_due_14_days_later_and_freezes_book_data(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        $book = $this->book('01');
        $item = $loan->addItem('i1', $book, 'Novela');

        $this->assertSame('2026-10-28T14:15:00+00:00', $item->dueAt->format(DATE_ATOM));
        $this->assertSame('Libro 01', $item->bookTitle);
        $this->assertSame('Novela', $item->categoryName);
        $this->assertSame(0, $book->availableCopies(), 'el ejemplar se descuenta en la misma operación');

        $book->update('Título nuevo', 'Autor', 'cat', Quantity::of(1));
        $this->assertSame('Libro 01', $loan->item('i1')->bookTitle, 'lo congelado no sigue al catálogo');
    }

    public function test_at_most_three_items(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        foreach (['01', '02', '03'] as $id) {
            $loan->addItem("i{$id}", $this->book($id), 'Novela');
        }
        $this->expectException(ValidationFailed::class);
        $loan->addItem('i04', $this->book('04'), 'Novela');
    }

    public function test_same_book_cannot_repeat(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        $book = $this->book('01', 3);
        $loan->addItem('i1', $book, 'Novela');
        $this->expectException(DuplicateBookInLoan::class);
        $loan->addItem('i2', $book, 'Novela');
    }

    public function test_cannot_lend_without_copies(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        $book = $this->book('01');
        $book->withdraw();
        $this->expectException(BookNotAvailable::class);
        $loan->addItem('i1', $book, 'Novela');
    }

    public function test_status_is_open_overdue_and_closed(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        $book = $this->book('01');
        $loan->addItem('i1', $book, 'Novela');

        $this->assertSame('open', $loan->status($this->loanedAt->modify('+14 days')));
        $this->assertSame('overdue', $loan->status($this->loanedAt->modify('+14 days +1 second')));

        $loan->returnItem('i1', $book, $this->loanedAt->modify('+20 days'));
        $this->assertSame('closed', $loan->status($this->loanedAt->modify('+30 days')));
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_returning_twice_never_restocks_twice(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->loanedAt);
        $book = $this->book('01', 2);
        $loan->addItem('i1', $book, 'Novela');
        $loan->returnItem('i1', $book, $this->loanedAt);

        try {
            $loan->returnItem('i1', $book, $this->loanedAt);
            $this->fail('Debió rechazar la segunda devolución');
        } catch (ItemAlreadyReturned $e) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $e->errorCode());
        }
        $this->assertSame(2, $book->availableCopies());
    }

    public function test_days_late(): void
    {
        $due = new \DateTimeImmutable('2026-10-28T14:15:00Z');
        $this->assertSame(0, LoanItem::daysLate($due, $due));
        $this->assertSame(1, LoanItem::daysLate($due, $due->modify('+1 hour')));
        $this->assertSame(3, LoanItem::daysLate($due, $due->modify('+3 days')));
    }
}
