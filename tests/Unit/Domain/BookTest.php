<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/** T-04: Book nunca deja disponibles < 0 ni > total. */
final class BookTest extends TestCase
{
    private function book(int $total = 2): Book
    {
        return Book::create('b1', ' Cien años de soledad ', 'García Márquez', Isbn::fromString('9780307474728'), 'cat', Quantity::of($total));
    }

    public function test_new_book_starts_with_available_equal_to_total(): void
    {
        $book = $this->book(4);
        $this->assertSame(4, $book->availableCopies());
        $this->assertSame('Cien años de soledad', $book->title());
        $this->assertNull($book->coverKey());
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $book = $this->book(1);
        $book->withdraw();
        $this->assertSame(0, $book->availableCopies());
        $this->expectException(BookNotAvailable::class);
        $book->withdraw();
    }

    public function test_restock_never_exceeds_total(): void
    {
        $book = $this->book(1);
        $this->expectException(\LogicException::class);
        $book->restock();
    }

    public function test_changing_total_adjusts_available_by_same_difference(): void
    {
        $book = $this->book(4);
        $book->withdraw(); // 3 disponibles
        $this->assertSame(2, $book->update('T', 'A', 'cat', Quantity::of(6)));
        $this->assertSame(5, $book->availableCopies());
        $this->assertSame(-3, $book->update('T', 'A', 'cat', Quantity::of(3)));
        $this->assertSame(2, $book->availableCopies());
    }

    public function test_total_cannot_drop_below_lent_copies(): void
    {
        $book = $this->book(2);
        $book->withdraw();
        $book->withdraw();
        try {
            $book->update('T', 'A', 'cat', Quantity::of(1));
            $this->fail('Debió rechazar el cambio');
        } catch (ValidationFailed $e) {
            $this->assertSame('total_copies', $e->details()[0]['field']);
        }
        $this->assertSame(0, $book->availableCopies());
        $this->assertSame(2, $book->totalCopies());
    }

    public function test_logical_delete_keeps_first_instant(): void
    {
        $book = $this->book();
        $first = new \DateTimeImmutable('2026-10-01T00:00:00Z');
        $book->delete($first);
        $book->delete(new \DateTimeImmutable('2026-10-02T00:00:00Z'));
        $this->assertEquals($first, $book->deletedAt());
    }

    public function test_remove_cover_returns_previous_key_and_leaves_null(): void
    {
        $book = $this->book();
        $book->setCover('abc.png');
        $this->assertSame('abc.png', $book->removeCover());
        $this->assertNull($book->coverKey());
    }
}
