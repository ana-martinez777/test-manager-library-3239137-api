<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Library\Domain\Errors\InvalidDateRange;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/** T-03: Isbn, Quantity, DateRange. */
final class ValueObjectsTest extends TestCase
{
    public function test_isbn_accepts_exactly_13_digits(): void
    {
        $this->assertSame('9780307474728', Isbn::fromString(' 9780307474728 ')->value);
    }

    public function test_isbn_rejects_wrong_length_or_non_digits(): void
    {
        foreach (['978030747472', '97803074747281', '978-030747472', 'abcdefghijklm'] as $bad) {
            try {
                Isbn::fromString($bad);
                $this->fail("Debió rechazar {$bad}");
            } catch (ValidationFailed $e) {
                $this->assertSame('VALIDATION_ERROR', $e->errorCode());
            }
        }
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $this->assertSame(1, Quantity::of(1)->value);
        $this->expectException(ValidationFailed::class);
        Quantity::of(0);
    }

    public function test_date_range_end_must_not_be_before_start(): void
    {
        $range = DateRange::of(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-01'));
        $this->assertSame('2026-10-02', $range->endExclusive()->format('Y-m-d'));

        $this->expectException(InvalidDateRange::class);
        DateRange::of(new \DateTimeImmutable('2026-10-31'), new \DateTimeImmutable('2026-10-01'));
    }
}
