<?php

declare(strict_types=1);

namespace Library\Domain\Model;

use DateTimeImmutable;
use Library\Domain\Errors\ItemAlreadyReturned;

/** Línea de préstamo. Congela título, ISBN y nombre de categoría (RN-4, ADR-004). */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    /** CA-07.2: una línea ya devuelta no se devuelve otra vez. */
    public function markReturned(DateTimeImmutable $at): void
    {
        if ($this->returnedAt !== null) {
            throw new ItemAlreadyReturned("La línea de «{$this->bookTitle}» ya fue devuelta.");
        }
        $this->returnedAt = $at;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    /** CA-09.1: sin devolver y con vencimiento anterior a ahora. */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return $this->returnedAt === null && $this->dueAt < $now;
    }

    /** Días de retraso: días iniciados desde el vencimiento (0 si no está vencida). */
    public static function daysLate(DateTimeImmutable $dueAt, DateTimeImmutable $now): int
    {
        $seconds = $now->getTimestamp() - $dueAt->getTimestamp();
        if ($seconds <= 0) {
            return 0;
        }

        return intdiv($seconds + 86399, 86400);
    }
}
