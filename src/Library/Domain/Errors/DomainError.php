<?php

declare(strict_types=1);

namespace Library\Domain\Errors;

/**
 * Error de negocio. Lleva un código estable (api-contract.md §2) y un mensaje en español.
 * No sabe nada de HTTP: el adaptador HTTP traduce el código a un estado (architecture.md §5).
 */
abstract class DomainError extends \RuntimeException
{
    /**
     * @param list<array{field: string, issue: string}> $details
     */
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    abstract public function errorCode(): string;

    /** @return list<array{field: string, issue: string}> */
    public function details(): array
    {
        return $this->details;
    }
}
