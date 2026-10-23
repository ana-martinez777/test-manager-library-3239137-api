<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\System;

use Library\Application\Ports\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
