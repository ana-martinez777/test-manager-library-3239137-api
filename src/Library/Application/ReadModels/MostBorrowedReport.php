<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

use Library\Domain\ValueObjects\DateRange;

final class MostBorrowedReport
{
    /** @param list<MostBorrowedRow> $rows */
    public function __construct(public readonly DateRange $range, public readonly array $rows)
    {
    }
}
