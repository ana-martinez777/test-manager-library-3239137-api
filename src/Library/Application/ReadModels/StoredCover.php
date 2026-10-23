<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

final class StoredCover
{
    public function __construct(public readonly string $bytes, public readonly string $mimeType)
    {
    }
}
