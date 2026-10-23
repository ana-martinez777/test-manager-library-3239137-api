<?php

declare(strict_types=1);

namespace Library\Application\Services\Loans;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\Ports\IdGenerator;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\DuplicateBookInLoan;
use Library\Domain\Errors\MemberHasOverdue;
use Library\Domain\Errors\MemberLimitExceeded;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Loan;

/**
 * HU-06: registrar un préstamo (architecture.md §4). Un servicio, un caso de uso (SRP).
 * Si el motor detecta que otro préstamo tomó el último ejemplar, reintenta una vez (ADR-002).
 */
final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
        private readonly IdGenerator $ids,
    ) {
    }

    /** @param list<string> $bookIds */
    public function lend(string $memberId, array $bookIds, string $loanedByUserId): Loan
    {
        $bookIds = array_values($bookIds);
        if ($bookIds === [] || count($bookIds) > Loan::MAX_ITEMS) {
            throw new ValidationFailed('Un préstamo debe tener entre 1 y 3 libros.', [['field' => 'items', 'issue' => 'entre 1 y 3 elementos']]);
        }
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new DuplicateBookInLoan(); // CA-06.2
        }

        $this->members->find($memberId) ?? throw new NotFound('El socio no existe.', [['field' => 'member_id', 'issue' => 'no existe']]);

        $now = $this->clock->now();
        if ($this->loans->memberHasOverdue($memberId, $now)) {
            throw new MemberHasOverdue(); // CA-06.5
        }
        $current = $this->loans->countUnreturnedByMember($memberId);
        if ($current + count($bookIds) > Loan::MAX_UNRETURNED_PER_MEMBER) {
            throw new MemberLimitExceeded(
                "El socio tiene {$current} libro(s) sin devolver y el máximo es " . Loan::MAX_UNRETURNED_PER_MEMBER . '.',
                [['field' => 'items', 'issue' => "sin devolver actualmente: {$current}"]]
            ); // CA-06.4
        }

        $categoryNames = [];
        foreach ($this->categories->all() as $category) {
            $categoryNames[$category->id] = $category->name;
        }

        for ($attempt = 1; ; $attempt++) {
            [$loan, $books] = $this->buildLoan($memberId, $bookIds, $loanedByUserId, $now, $categoryNames);
            try {
                $this->loans->add($loan, $books);

                return $loan;
            } catch (ConcurrencyConflict) {
                if ($attempt >= 2) {
                    throw new BookNotAvailable('Otro préstamo tomó el último ejemplar disponible. Intenta de nuevo.');
                }
            }
        }
    }

    /**
     * @param list<string> $bookIds
     * @param array<string, string> $categoryNames
     * @return array{0: Loan, 1: list<\Library\Domain\Model\Book>}
     */
    private function buildLoan(string $memberId, array $bookIds, string $userId, \DateTimeImmutable $now, array $categoryNames): array
    {
        $books = $this->books->findActiveByIds($bookIds);
        $loan = Loan::open($this->ids->newId(), $memberId, $userId, $now);

        foreach ($bookIds as $index => $bookId) {
            $book = $books[$bookId] ?? throw new NotFound(
                'El libro no existe.',
                [['field' => "items[{$index}].book_id", 'issue' => 'no existe']]
            );
            try {
                // Descontar el ejemplar y crear la línea son una sola operación (CA-06.3).
                $loan->addItem($this->ids->newId(), $book, $categoryNames[$book->categoryId()] ?? '');
            } catch (BookNotAvailable $e) {
                throw new BookNotAvailable($e->getMessage(), [['field' => "items[{$index}].book_id", 'issue' => 'sin ejemplares']]);
            }
        }

        return [$loan, array_values($books)];
    }
}
