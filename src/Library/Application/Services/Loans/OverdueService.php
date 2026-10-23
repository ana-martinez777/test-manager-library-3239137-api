<?php

declare(strict_types=1);

namespace Library\Application\Services\Loans;

use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Application\ReadModels\OverdueLine;

/** HU-09: líneas vencidas, la de más días de retraso primero. */
final class OverdueService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    /** @return list<OverdueLine> */
    public function list(): array
    {
        $lines = $this->loans->overdue($this->clock->now());
        usort($lines, static fn (OverdueLine $a, OverdueLine $b) => [$b->daysLate, $a->dueAt] <=> [$a->daysLate, $b->dueAt]);

        return $lines;
    }
}
