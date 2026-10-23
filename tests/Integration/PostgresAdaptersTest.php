<?php

declare(strict_types=1);

namespace Tests\Integration;

use Library\Adapters\Outbound\Persistence\PgBookRepository;
use Library\Adapters\Outbound\Persistence\PgCategoryRepository;
use Library\Adapters\Outbound\Persistence\PgLoanRepository;
use Library\Adapters\Outbound\Persistence\PgMemberRepository;
use Library\Adapters\Outbound\Persistence\PgReportReader;
use Library\Adapters\Outbound\Persistence\PgUserRepository;
use Library\Adapters\Outbound\System\RandomUuidGenerator;
use Library\Application\Errors\DocumentTaken;
use Library\Application\Errors\IsbnTaken;
use Library\Application\Errors\UsernameTaken;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\Services\Books\CreateBookService;
use Library\Application\Services\Books\DeleteBookService;
use Library\Application\Services\Books\UpdateBookService;
use Library\Application\Services\Loans\LendBooksService;
use Library\Application\Services\Loans\ReturnItemService;
use Library\Application\Services\Members\CreateMemberService;
use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\ItemAlreadyReturned;
use Library\Domain\Model\Book;
use Library\Domain\Model\Loan;
use Library\Domain\Model\User;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Tests\Support\Fakes\FixedClock;

/** T-12, T-13, T-11 (ADR-004) y T-18 contra PostgreSQL real. */
final class PostgresAdaptersTest extends PostgresTestCase
{
    private const NOVELA = '11111111-1111-4111-8111-111111111111';
    private const CIENCIA = '22222222-2222-4222-8222-222222222222';

    private PgBookRepository $books;
    private PgCategoryRepository $categories;
    private PgMemberRepository $members;
    private PgUserRepository $users;
    private PgLoanRepository $loans;
    private RandomUuidGenerator $ids;
    private FixedClock $clock;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->books = new PgBookRepository($this->pdo);
        $this->categories = new PgCategoryRepository($this->pdo);
        $this->members = new PgMemberRepository($this->pdo);
        $this->users = new PgUserRepository($this->pdo);
        $this->loans = new PgLoanRepository($this->pdo);
        $this->ids = new RandomUuidGenerator();
        $this->clock = new FixedClock('2026-10-14T14:15:00Z');

        $user = User::createLibrarian($this->ids->newId(), 'it-' . bin2hex(random_bytes(4)), 'hash', 'librarian');
        $this->users->add($user);
        $this->userId = $user->id;
    }

    private function isbn(): string
    {
        return '9' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
    }

    private function newBook(string $title = 'Cien años de soledad', int $copies = 2): string
    {
        return (new CreateBookService($this->books, $this->categories, $this->ids))
            ->create($title, 'García Márquez', $this->isbn(), self::NOVELA, $copies)->book->id;
    }

    private function newMember(): string
    {
        return (new CreateMemberService($this->members, $this->ids))->create('Ana Pérez', 'IT-' . bin2hex(random_bytes(5)))->id;
    }

    private function lend(): LendBooksService
    {
        return new LendBooksService($this->members, $this->books, $this->categories, $this->loans, $this->clock, $this->ids);
    }

    public function test_categories_are_seeded_and_sorted(): void
    {
        $names = array_map(static fn ($c) => $c->name, $this->categories->all());
        $this->assertSame(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología'], $names);
    }

    public function test_book_round_trip_and_partial_unique_isbn(): void
    {
        $isbn = $this->isbn();
        $book = Book::create($this->ids->newId(), 'Título', 'Autor', Isbn::fromString($isbn), self::NOVELA, Quantity::of(3));
        $this->books->add($book);

        $loaded = $this->books->findActive($book->id);
        $this->assertSame($isbn, $loaded->isbn()->value);
        $this->assertSame(3, $loaded->availableCopies());
        $this->assertNull($loaded->coverKey());

        $this->pdo->exec('SAVEPOINT dup');
        try {
            $this->books->add(Book::create($this->ids->newId(), 'Otro', 'Autor', Isbn::fromString($isbn), self::NOVELA, Quantity::of(1)));
            $this->fail('El índice parcial debió rechazar el ISBN activo repetido');
        } catch (IsbnTaken) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT dup');
        }

        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($book->id);
        $this->assertNull($this->books->findActive($book->id));
        $this->assertNotNull($this->books->findAny($book->id));
        $this->books->add(Book::create($this->ids->newId(), 'Reedición', 'Autor', Isbn::fromString($isbn), self::NOVELA, Quantity::of(1)));
        $this->assertTrue($this->books->existsActiveIsbn($isbn));
    }

    public function test_search_is_partial_case_insensitive_and_excludes_deleted(): void
    {
        $tag = 'Zq' . bin2hex(random_bytes(3));
        $this->newBook("{$tag} beta");
        $this->newBook("{$tag} alfa");
        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($this->newBook("{$tag} gamma"));

        $page = $this->books->search(strtolower($tag), self::NOVELA, 1, 50);
        $this->assertSame(2, $page->total);
        $this->assertSame(["{$tag} alfa", "{$tag} beta"], array_map(static fn ($b) => $b->title(), $page->items));
        $this->assertSame(0, $this->books->search($tag, self::CIENCIA, 1, 50)->total);
    }

    public function test_unique_member_document_and_username(): void
    {
        $doc = 'DOC-' . bin2hex(random_bytes(4));
        (new CreateMemberService($this->members, $this->ids))->create('Uno', $doc);
        $this->pdo->exec('SAVEPOINT dup');
        try {
            $this->members->add(\Library\Domain\Model\Member::create($this->ids->newId(), 'Dos', \Library\Domain\ValueObjects\DocumentNumber::fromString($doc)));
            $this->fail('El índice único debió rechazar el documento');
        } catch (DocumentTaken) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT dup');
        }

        $name = 'user-' . bin2hex(random_bytes(4));
        $this->users->add(User::createLibrarian($this->ids->newId(), $name, 'h', 'librarian'));
        $this->expectException(UsernameTaken::class);
        $this->users->add(User::createLibrarian($this->ids->newId(), strtoupper($name), 'h', 'librarian'));
    }

    public function test_loan_round_trip_status_and_return_once(): void
    {
        $bookId = $this->newBook(copies: 1);
        $memberId = $this->newMember();
        $loan = $this->lend()->lend($memberId, [$bookId], $this->userId);

        $this->assertSame(0, $this->books->findActive($bookId)->availableCopies());
        $loaded = $this->loans->find($loan->id);
        $this->assertSame('Cien años de soledad', $loaded->items()[0]->bookTitle);
        $this->assertSame('Novela', $loaded->items()[0]->categoryName);
        $this->assertSame('2026-10-28T14:15:00+00:00', $loaded->items()[0]->dueAt->format(DATE_ATOM));
        $this->assertSame(1, $this->loans->countUnreturnedByMember($memberId));
        $this->assertTrue($this->loans->bookHasOpenLoans($bookId));
        $this->assertTrue($this->loans->memberHasOverdue($memberId, $this->clock->now()->modify('+15 days')));
        $this->assertSame(1, $this->loans->search(LoanSearchCriteria::of(null, null, 'open', $memberId), $this->clock->now(), 1, 20)->total);

        $return = new ReturnItemService($this->loans, $this->books, $this->clock);
        $return->returnItem($loan->id, $loan->items()[0]->id);
        $this->assertSame(1, $this->books->findActive($bookId)->availableCopies());
        try {
            $return->returnItem($loan->id, $loan->items()[0]->id);
            $this->fail('Segunda devolución');
        } catch (ItemAlreadyReturned) {
        }
        $this->assertSame(1, $this->books->findActive($bookId)->availableCopies(), 'nunca suma dos veces');
        $this->assertSame(Loan::STATUS_CLOSED, $this->loans->find($loan->id)->status($this->clock->now()));
        $this->assertSame(1, $this->loans->search(LoanSearchCriteria::of(null, null, 'closed', $memberId), $this->clock->now(), 1, 20)->total);
    }

    public function test_stale_copy_count_is_rejected_by_the_engine(): void
    {
        // Simula dos bibliotecarios que leyeron available_copies = 1 a la vez (RNF-2):
        // el primero guarda, el segundo debe recibir conflicto y no se guarda nada suyo.
        $bookId = $this->newBook(copies: 1);
        $memberA = $this->newMember();
        $memberB = $this->newMember();

        $staleBook = $this->books->findActive($bookId);
        $this->lend()->lend($memberA, [$bookId], $this->userId);

        $loan = Loan::open($this->ids->newId(), $memberB, $this->userId, $this->clock->now());
        $loan->addItem($this->ids->newId(), $staleBook, 'Novela'); // el dominio cree que hay 1
        try {
            $this->loans->add($loan, [$staleBook]);
            $this->fail('El motor debió rechazar el segundo préstamo');
        } catch (ConcurrencyConflict) {
        }
        $this->assertNull($this->loans->find($loan->id), 'no se registró nada');
        $this->assertSame(0, $this->books->findActive($bookId)->availableCopies());

        $this->expectException(BookNotAvailable::class);
        $this->lend()->lend($memberB, [$bookId], $this->userId);
    }

    public function test_report_groups_by_frozen_values_after_rename(): void
    {
        // ADR-004: prestar, renombrar, pedir el reporte: el título del reporte es el viejo.
        $bookId = $this->newBook('Título viejo', 5);
        $this->lend()->lend($this->newMember(), [$bookId], $this->userId);
        $this->lend()->lend($this->newMember(), [$bookId], $this->userId);

        (new UpdateBookService($this->books, $this->categories))->update($bookId, 'Título nuevo', 'Autor', self::CIENCIA, 5);
        $this->clock->advance('+1 hour');
        $this->lend()->lend($this->newMember(), [$bookId], $this->userId);

        $rows = (new PgReportReader($this->pdo))->mostBorrowed(DateRange::of(new \DateTimeImmutable('2026-10-14'), new \DateTimeImmutable('2026-10-14')));
        $mine = array_values(array_filter($rows, static fn ($r) => $r->bookId === $bookId));

        $this->assertCount(2, $mine, 'una fila por libro y valores congelados');
        $this->assertSame(['Título viejo', 'Novela', 2], [$mine[0]->bookTitle, $mine[0]->categoryName, $mine[0]->timesBorrowed]);
        $this->assertSame(['Título nuevo', 'Ciencia', 1], [$mine[1]->bookTitle, $mine[1]->categoryName, $mine[1]->timesBorrowed]);

        $empty = (new PgReportReader($this->pdo))->mostBorrowed(DateRange::of(new \DateTimeImmutable('1990-01-01'), new \DateTimeImmutable('1990-01-31')));
        $this->assertSame([], $empty);
    }

    public function test_engine_enforces_checks(): void
    {
        // ck_book_available_range (motor) y, tras T-18, ck_book_total_positive y ck_user_role.
        $bookId = $this->newBook(copies: 1);
        foreach ([
            "UPDATE library.book SET available_copies = 2 WHERE id = '{$bookId}'",
            "UPDATE library.book SET available_copies = -1 WHERE id = '{$bookId}'",
            "UPDATE library.book SET total_copies = 0, available_copies = 0 WHERE id = '{$bookId}'",
            "UPDATE library.\"user\" SET role = 'member' WHERE id = '{$this->userId}'",
        ] as $sql) {
            $this->pdo->exec('SAVEPOINT chk');
            try {
                $this->pdo->exec($sql);
                $this->fail("El motor debió rechazar: {$sql}");
            } catch (\PDOException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->pdo->exec('ROLLBACK TO SAVEPOINT chk');
        }
    }

    public function test_no_column_has_default(): void
    {
        $count = $this->pdo->query("SELECT count(*) FROM information_schema.columns WHERE table_schema = 'library' AND column_default IS NOT NULL")->fetchColumn();
        $this->assertSame(0, (int) $count);
    }
}
