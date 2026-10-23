<?php

declare(strict_types=1);

namespace Library\Application\Ports;

/** Reloj como puerto (plan D-05): permite probar vencimientos sin esperar 14 días. Siempre UTC. */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
