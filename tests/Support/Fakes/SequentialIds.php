<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Ports\IdGenerator;

final class SequentialIds implements IdGenerator
{
    private int $next = 1;

    public function newId(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $this->next++);
    }
}
