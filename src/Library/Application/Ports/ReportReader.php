<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\MostBorrowedRow;
use Library\Domain\ValueObjects\DateRange;

/** Puerto de lectura del reporte (ADR-004): una consulta agregada sobre valores congelados. */
interface ReportReader
{
    /** @return list<MostBorrowedRow> */
    public function mostBorrowed(DateRange $range): array;
}
