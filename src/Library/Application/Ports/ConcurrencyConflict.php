<?php

declare(strict_types=1);

namespace Library\Application\Ports;

/**
 * Lo lanza un repositorio cuando la escritura condicionada no afecta filas porque otro proceso
 * cambió el dato entre la lectura y la escritura (ADR-002). Los casos de uso lo traducen.
 */
final class ConcurrencyConflict extends \RuntimeException
{
}
