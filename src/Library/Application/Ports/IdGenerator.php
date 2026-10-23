<?php

declare(strict_types=1);

namespace Library\Application\Ports;

/** Genera identificadores UUID v4 (api-contract.md §1). */
interface IdGenerator
{
    public function newId(): string;
}
