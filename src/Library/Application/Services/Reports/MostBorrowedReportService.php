<?php

declare(strict_types=1);

namespace Library\Application\Services\Reports;

use Library\Application\Ports\ReportReader;
use Library\Application\ReadModels\MostBorrowedReport;
use Library\Domain\ValueObjects\DateRange;

/** HU-10: reporte de más prestados sobre valores congelados (ADR-004). */
final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reader)
    {
    }

    public function generate(\DateTimeImmutable $from, \DateTimeImmutable $to): MostBorrowedReport
    {
        $range = DateRange::of($from, $to); // CA-10.2: fin < inicio → 422

        return new MostBorrowedReport($range, $this->reader->mostBorrowed($range));
    }
}
