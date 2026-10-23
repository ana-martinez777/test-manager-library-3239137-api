<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\StoredCover;

/** Almacenamiento del binario de portadas (plan D-08). La base solo guarda la clave opaca. */
interface CoverStorage
{
    /** Guarda el binario y devuelve una clave opaca. */
    public function put(string $bytes, string $extension): string;

    public function get(string $key): ?StoredCover;

    public function delete(string $key): void;
}
