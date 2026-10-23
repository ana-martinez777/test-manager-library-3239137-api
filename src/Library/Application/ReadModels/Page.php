<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

/**
 * @template T
 */
final class Page
{
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $size,
        public readonly int $total,
    ) {
    }

    /**
     * @template U
     * @param callable(T): U $fn
     * @return Page<U>
     */
    public function map(callable $fn): self
    {
        return new self(array_map($fn, $this->items), $this->page, $this->size, $this->total);
    }
}
