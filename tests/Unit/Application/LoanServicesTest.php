<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Library\Application\Errors\NotFound;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\ReadModels\PageRequest;
use Library\Application\Services\Books\CreateBookService;
use Library\Application\Services\Loans\LendBooksService;
use Library\Application\Services\Loans\OverdueService;
use Library\Application\Services\Loans\ReturnItemService;
use Library\Application\Services\Loans\SearchLoansService;
use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\DuplicateBookInLoan;
use Library\Domain\Errors\InvalidDateRange;
use Library\Domain\Errors\ItemAlreadyReturned;
use Library\Domain\Errors\MemberHasOverdue;
use Library\Domain\Errors\MemberLimitExceeded;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Member;
use Library\Domain\ValueObjects\DocumentNumber;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryBooks;
use Tests\Support\Fakes\InMemoryCategories;
use Tests\Support\Fakes\InMemoryLoans;
use Tests\Support\Fakes\InMemoryMembers;
use Tests\Support\Fakes\SequentialIds;

/** T-09 y T-10: HU-06, HU-07 y HU-09 contra fakes. */
final class LoanServicesTest extends TestCase
{
    private InMemoryBooks $books;
    private InMemoryMembers $members;
    private InMemoryLoans $loans;
    private FixedClock $clock;
    private LendBooksService $lend;
    private ReturnItemService $return;
    /** @var list<string> */
    private array $bookIds = [];

    protected function setUp(): void
    {
        $this->books = new InMemoryBooks();
        $this->members = new InMemoryMembers();
        $this->loans = new InMemoryLoans($this->books, $this->members);
        $this->clock = new FixedClock();
        $ids = new SequentialIds();
        $categories = new InMemoryCategories();

        $create = new CreateBookService($this->books, $categories, $ids);
        foreach (range(1, 5) as $n) {
            $this->bookIds[] = $create->create("Libro {$n}", 'Autor', '100000000000' . $n, InMemoryCategories::NOVELA, $n === 5 ? 1 : 3)->book->id;
        }
        $this->members->add(Member::create('m1', 'Ana Pérez', DocumentNumber::fromString('111')));
        $this->members->add(Member::create('m2', 'Luis Gómez', DocumentNumber::fromString('222')));

        $this->lend = new LendBooksService($this->members, $this->books, $categories, $this->loans, $this->clock, $ids);
        $this->return = new ReturnItemService($this->loans, $this->books, $this->clock);
    }

    public function test_lend_discounts_one_copy_per_item_and_freezes_data(): void
    {
        $loan = $this->lend->lend('m1', [$this->bookIds[0], $this->bookIds[1]], 'u1');

        $this->assertCount(2, $loan->items());
        $this->assertSame('open', $loan->status($this->clock->now()));
        $this->assertSame('Novela', $loan->items()[0]->categoryName);
        $this->assertSame(2, $this->books->findActive($this->bookIds[0])->availableCopies());
        $this->assertSame(2, $this->books->findActive($this->bookIds[1])->availableCopies());
    }

    public function test_items_must_be_between_one_and_three_and_not_repeated(): void
    {
        foreach ([[], array_slice($this->bookIds, 0, 4)] as $items) {
            try {
                $this->lend->lend('m1', $items, 'u1');
                $this->fail('Debió rechazar la cantidad de líneas');
            } catch (ValidationFailed) {
            }
        }
        $this->expectException(DuplicateBookInLoan::class);
        $this->lend->lend('m1', [$this->bookIds[0], $this->bookIds[0]], 'u1');
    }

    public function test_without_copies_nothing_is_registered(): void
    {
        $this->lend->lend('m2', [$this->bookIds[4]], 'u1'); // agota el único ejemplar
        try {
            $this->lend->lend('m1', [$this->bookIds[0], $this->bookIds[4]], 'u1');
            $this->fail('Debió fallar por falta de ejemplares');
        } catch (BookNotAvailable $e) {
            $this->assertSame('items[1].book_id', $e->details()[0]['field']);
        }
        $this->assertSame(3, $this->books->findActive($this->bookIds[0])->availableCopies(), 'no se descontó nada');
        $this->assertSame(0, $this->loans->countUnreturnedByMember('m1'));
    }

    public function test_member_cannot_exceed_three_unreturned_books(): void
    {
        $this->lend->lend('m1', [$this->bookIds[0], $this->bookIds[1]], 'u1');
        try {
            $this->lend->lend('m1', [$this->bookIds[2], $this->bookIds[3]], 'u1');
            $this->fail('Debió superar el límite');
        } catch (MemberLimitExceeded $e) {
            $this->assertStringContainsString('2', $e->getMessage(), 'informa el conteo actual');
        }
        $this->assertCount(1, $this->lend->lend('m1', [$this->bookIds[2]], 'u1')->items());
    }

    public function test_member_with_overdue_cannot_borrow(): void
    {
        $this->lend->lend('m1', [$this->bookIds[0]], 'u1');
        $this->clock->advance('+15 days');
        $this->expectException(MemberHasOverdue::class);
        $this->lend->lend('m1', [$this->bookIds[1]], 'u1');
    }

    public function test_unknown_member_or_book_is_not_found(): void
    {
        try {
            $this->lend->lend('nadie', [$this->bookIds[0]], 'u1');
            $this->fail('Socio inexistente');
        } catch (NotFound) {
        }
        $this->expectException(NotFound::class);
        $this->lend->lend('m1', ['99999999-9999-4999-8999-999999999999'], 'u1');
    }

    public function test_race_for_last_copy_retries_once_then_conflicts(): void
    {
        $this->books->stealLastCopyOnNextLoan = $this->bookIds[4];
        $this->expectException(BookNotAvailable::class);
        $this->lend->lend('m1', [$this->bookIds[4]], 'u1');
    }

    public function test_return_restocks_once_and_second_return_is_409(): void
    {
        $loan = $this->lend->lend('m1', [$this->bookIds[0], $this->bookIds[1]], 'u1');
        [$first, $second] = $loan->items();

        $returned = $this->return->returnItem($loan->id, $first->id);
        $this->assertNotNull($returned->returnedAt());
        $this->assertSame(3, $this->books->findActive($this->bookIds[0])->availableCopies());

        try {
            $this->return->returnItem($loan->id, $first->id);
            $this->fail('Debió rechazar la segunda devolución');
        } catch (ItemAlreadyReturned) {
        }
        $this->assertSame(3, $this->books->findActive($this->bookIds[0])->availableCopies(), 'nunca suma dos veces');

        $this->return->returnItem($loan->id, $second->id);
        $this->assertSame('closed', $this->loans->find($loan->id)->status($this->clock->now()));
    }

    public function test_overdue_lists_most_late_first(): void
    {
        $this->lend->lend('m1', [$this->bookIds[0]], 'u1');
        $this->clock->advance('+2 days');
        $this->lend->lend('m2', [$this->bookIds[1]], 'u1');
        $this->clock->advance('+20 days');

        $lines = (new OverdueService($this->loans, $this->clock))->list();
        $this->assertCount(2, $lines);
        $this->assertSame('Ana Pérez', $lines[0]->memberName);
        $this->assertSame(8, $lines[0]->daysLate);
        $this->assertSame(6, $lines[1]->daysLate);
    }

    public function test_search_loans_by_status(): void
    {
        $loan = $this->lend->lend('m1', [$this->bookIds[0]], 'u1');
        $this->return->returnItem($loan->id, $loan->items()[0]->id);
        $this->lend->lend('m2', [$this->bookIds[1]], 'u1');

        $search = new SearchLoansService($this->loans, $this->clock);
        $this->assertSame(1, $search->search(LoanSearchCriteria::of(null, null, 'closed', null), PageRequest::of())->total);
        $this->assertSame(1, $search->search(LoanSearchCriteria::of(null, null, 'open', null), PageRequest::of())->total);

        $this->expectException(InvalidDateRange::class);
        LoanSearchCriteria::of(new \DateTimeImmutable('2026-10-10'), new \DateTimeImmutable('2026-10-01'), null, null);
    }
}
