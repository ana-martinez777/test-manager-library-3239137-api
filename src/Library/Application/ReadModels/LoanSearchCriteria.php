<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

use DateTimeImmutable;
use Library\Domain\Errors\InvalidDateRange;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Loan;

/** Filtros de CA-08.2: rango de fechas (inclusive), estado y socio. */
final class LoanSearchCriteria
{
    private function __construct(
        public readonly ?DateTimeImmutable $fromInclusive,
        public readonly ?DateTimeImmutable $toExclusive,
        public readonly ?string $status,
        public readonly ?string $memberId,
    ) {
    }

    public static function of(?DateTimeImmutable $from, ?DateTimeImmutable $to, ?string $status, ?string $memberId): self
    {
        $utc = new \DateTimeZone('UTC');
        $from = $from?->setTimezone($utc)->setTime(0, 0);
        $to = $to?->setTimezone($utc)->setTime(0, 0);
        if ($from !== null && $to !== null && $to < $from) {
            throw new InvalidDateRange('La fecha final no puede ser anterior a la inicial.', [['field' => 'to', 'issue' => 'anterior a from']]);
        }
        $allowed = [Loan::STATUS_OPEN, Loan::STATUS_OVERDUE, Loan::STATUS_CLOSED];
        if ($status !== null && !in_array($status, $allowed, true)) {
            throw new ValidationFailed('El estado no es válido.', [['field' => 'status', 'issue' => 'debe ser open, overdue o closed']]);
        }

        return new self($from, $to?->modify('+1 day'), $status, $memberId);
    }
}
