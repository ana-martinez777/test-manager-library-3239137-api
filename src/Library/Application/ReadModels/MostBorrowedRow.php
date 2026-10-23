<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

/** Fila del reporte: sin socios ni bibliotecarios (CA-10.3). */
final class MostBorrowedRow
{
    public function __construct(
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $categoryName,
        public readonly int $timesBorrowed,
    ) {
    }
}
