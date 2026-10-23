<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Errors\InvalidDateRange;

/**
 * Rango de fechas de calendario, ambos extremos inclusive (CA-10.2).
 * Se interpreta en UTC: [from 00:00Z, to+1 día 00:00Z).
 */
final class DateRange
{
    private function __construct(public readonly DateTimeImmutable $from, public readonly DateTimeImmutable $to)
    {
    }

    public static function of(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        $utc = new DateTimeZone('UTC');
        $from = $from->setTimezone($utc)->setTime(0, 0);
        $to = $to->setTimezone($utc)->setTime(0, 0);
        if ($to < $from) {
            throw new InvalidDateRange(
                'La fecha final no puede ser anterior a la inicial.',
                [['field' => 'to', 'issue' => 'anterior a from']]
            );
        }

        return new self($from, $to);
    }

    public function startInclusive(): DateTimeImmutable
    {
        return $this->from;
    }

    public function endExclusive(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }
}
