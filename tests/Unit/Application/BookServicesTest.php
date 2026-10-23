<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Library\Application\Errors\BookHasOpenLoans;
use Library\Application\Errors\IsbnTaken;
use Library\Application\Errors\NotFound;
use Library\Application\Ports\CoverStorage;
use Library\Application\ReadModels\PageRequest;
use Library\Application\ReadModels\StoredCover;
use Library\Application\Services\Books\BookViews;
use Library\Application\Services\Books\CreateBookService;
use Library\Application\Services\Books\DeleteBookService;
use Library\Application\Services\Books\GetBookService;
use Library\Application\Services\Books\RemoveCoverService;
use Library\Application\Services\Books\SearchBooksService;
use Library\Application\Services\Books\UpdateBookService;
use Library\Application\Services\Books\UploadCoverService;
use Library\Application\Services\Loans\LendBooksService;
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

/** T-08: HU-03 y HU-04 contra fakes. */
final class BookServicesTest extends TestCase
{
    private InMemoryBooks $books;
    private InMemoryCategories $categories;
    private InMemoryMembers $members;
    private InMemoryLoans $loans;
    private SequentialIds $ids;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->books = new InMemoryBooks();
        $this->categories = new InMemoryCategories();
        $this->members = new InMemoryMembers();
        $this->loans = new InMemoryLoans($this->books, $this->members);
        $this->ids = new SequentialIds();
        $this->clock = new FixedClock();
    }

    private function create(string $title = 'Cien años de soledad', string $isbn = '9780307474728', int $copies = 2): string
    {
        return (new CreateBookService($this->books, $this->categories, $this->ids))
            ->create($title, 'García Márquez', $isbn, InMemoryCategories::NOVELA, $copies)->book->id;
    }

    public function test_create_and_get_book_with_category(): void
    {
        $id = $this->create();
        $view = (new GetBookService($this->books, new BookViews($this->categories)))->get($id);
        $this->assertSame('Novela', $view->category->name);
        $this->assertSame(2, $view->book->availableCopies());
    }

    public function test_duplicate_active_isbn_is_409_but_reusable_after_delete(): void
    {
        $id = $this->create();
        try {
            $this->create('Otro', '9780307474728');
            $this->fail('Debió rechazar ISBN repetido');
        } catch (IsbnTaken) {
        }
        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($id);
        $this->assertNotSame($id, $this->create('Reedición', '9780307474728'));
    }

    public function test_unknown_category_is_validation_error(): void
    {
        $this->expectException(ValidationFailed::class);
        (new CreateBookService($this->books, $this->categories, $this->ids))->create('T', 'A', '9780307474728', '99999999-9999-4999-8999-999999999999', 1);
    }

    public function test_search_filters_active_and_orders_by_title(): void
    {
        $this->create('Zeta', '1000000000001');
        $this->create('Alfa', '1000000000002');
        $deleted = $this->create('Beta', '1000000000003');
        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($deleted);

        $page = (new SearchBooksService($this->books, new BookViews($this->categories)))->search(null, null, PageRequest::of(1, 20));
        $this->assertSame(2, $page->total);
        $this->assertSame(['Alfa', 'Zeta'], array_map(static fn ($v) => $v->book->title(), $page->items));
    }

    public function test_size_above_50_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        PageRequest::of(1, 51);
    }

    public function test_deleted_book_is_not_found(): void
    {
        $id = $this->create();
        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($id);
        $this->expectException(NotFound::class);
        (new GetBookService($this->books, new BookViews($this->categories)))->get($id);
    }

    public function test_book_with_open_loans_cannot_be_deleted(): void
    {
        $id = $this->create();
        $this->members->add(Member::create('m1', 'Ana', DocumentNumber::fromString('1')));
        (new LendBooksService($this->members, $this->books, $this->categories, $this->loans, $this->clock, $this->ids))->lend('m1', [$id], 'u1');

        $this->expectException(BookHasOpenLoans::class);
        (new DeleteBookService($this->books, $this->loans, $this->clock))->delete($id);
    }

    public function test_update_cannot_leave_available_below_zero(): void
    {
        $id = $this->create(copies: 2);
        $this->members->add(Member::create('m1', 'Ana', DocumentNumber::fromString('1')));
        (new LendBooksService($this->members, $this->books, $this->categories, $this->loans, $this->clock, $this->ids))->lend('m1', [$id], 'u1');

        $update = new UpdateBookService($this->books, $this->categories);
        $view = $update->update($id, 'Nuevo', 'Autor', InMemoryCategories::CIENCIA, 1);
        $this->assertSame(0, $view->book->availableCopies());
        $this->assertSame('Ciencia', $view->category->name);

        $this->expectException(ValidationFailed::class);
        $update->update($id, 'Nuevo', 'Autor', InMemoryCategories::CIENCIA, 0);
    }

    public function test_cover_upload_validates_content_and_size(): void
    {
        $id = $this->create();
        $storage = new class implements CoverStorage {
            public array $files = [];

            public function put(string $bytes, string $extension): string
            {
                $key = 'k' . count($this->files) . '.' . $extension;
                $this->files[$key] = $bytes;

                return $key;
            }

            public function get(string $key): ?StoredCover
            {
                return isset($this->files[$key]) ? new StoredCover($this->files[$key], 'image/png') : null;
            }

            public function delete(string $key): void
            {
                unset($this->files[$key]);
            }
        };
        $upload = new UploadCoverService($this->books, $storage, new BookViews($this->categories));
        $png = "\x89PNG\r\n\x1A\n" . str_repeat('x', 100);

        $first = $upload->upload($id, $png)->book->coverKey();
        $second = $upload->upload($id, $png)->book->coverKey();
        $this->assertSame([$second], array_keys($storage->files), 'el binario anterior se borra');

        foreach (['GIF89a....', "\x89PNG\r\n\x1A\n" . str_repeat('x', 2 * 1024 * 1024)] as $bad) {
            try {
                $upload->upload($id, $bad);
                $this->fail('Debió rechazar la portada');
            } catch (ValidationFailed) {
            }
        }

        (new RemoveCoverService($this->books, $storage))->remove($id);
        $this->assertNull($this->books->findActive($id)->coverKey(), 'sin portada el campo es null');
        $this->assertSame([], $storage->files);
        $this->assertNotSame($first, $second);
    }
}
