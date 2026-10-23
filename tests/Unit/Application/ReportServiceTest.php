<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Library\Application\Ports\ReportReader;
use Library\Application\ReadModels\MostBorrowedRow;
use Library\Application\Services\Reports\MostBorrowedReportService;
use Library\Domain\Errors\InvalidDateRange;
use Library\Domain\ValueObjects\DateRange;
use PHPUnit\Framework\TestCase;

/** T-11: validación del rango y lista vacía sin error. La consulta real se prueba en Integration. */
final class ReportServiceTest extends TestCase
{
    public function test_empty_range_returns_empty_list_and_inverted_range_is_422(): void
    {
        $reader = new class implements ReportReader {
            public function mostBorrowed(DateRange $range): array
            {
                return [];
            }
        };
        $service = new MostBorrowedReportService($reader);

        $this->assertSame([], $service->generate(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31'))->rows);

        $this->expectException(InvalidDateRange::class);
        $service->generate(new \DateTimeImmutable('2026-10-31'), new \DateTimeImmutable('2026-10-01'));
    }

    public function test_rows_carry_no_member_or_librarian_data(): void
    {
        $fields = array_keys(get_class_vars(MostBorrowedRow::class) ?: []);
        $reflection = new \ReflectionClass(MostBorrowedRow::class);
        $names = array_map(static fn ($p) => $p->getName(), $reflection->getProperties());
        $this->assertSame(['bookId', 'bookTitle', 'categoryName', 'timesBorrowed'], $names);
        $this->assertIsArray($fields);
    }
}
