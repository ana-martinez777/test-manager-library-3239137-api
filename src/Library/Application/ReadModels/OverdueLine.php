<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

final class OverdueLine
{
    public function __construct(
        public readonly string $loanId,
        public readonly string $itemId,
        public readonly string $bookTitle,
        public readonly string $memberName,
        public readonly \DateTimeImmutable $dueAt,
        public readonly int $daysLate,
    ) {
    }
}
