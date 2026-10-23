<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use DateTimeImmutable;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\ReadModels\OverdueLine;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Book;
use Library\Domain\Model\Loan;

interface LoanRepository
{
    public function find(string $id): ?Loan;

    /** Q7: líneas sin devolver del socio. */
    public function countUnreturnedByMember(string $memberId): int;

    /** Q7: ¿el socio tiene alguna línea sin devolver vencida? */
    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool;

    public function bookHasOpenLoans(string $bookId): bool;

    /**
     * Guarda el préstamo, sus líneas y el descuento de cada libro en UNA transacción.
     * Si un libro ya no tiene ejemplares en el motor, deshace todo y lanza ConcurrencyConflict.
     *
     * @param list<Book> $books
     */
    public function add(Loan $loan, array $books): void;

    /**
     * Guarda la devolución de la línea y suma el ejemplar en UNA transacción.
     * Si la línea ya estaba devuelta en el motor, lanza ConcurrencyConflict.
     */
    public function saveReturn(Loan $loan, string $itemId): void;

    /** Q6. @return Page<Loan> */
    public function search(LoanSearchCriteria $criteria, DateTimeImmutable $now, int $page, int $size): Page;

    /** Q8: líneas vencidas, la más atrasada primero. @return list<OverdueLine> */
    public function overdue(DateTimeImmutable $now): array;
}
