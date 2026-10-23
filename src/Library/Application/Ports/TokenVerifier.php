<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\TokenClaims;

interface TokenVerifier
{
    /** Devuelve los datos del token o null si es inválido o venció. */
    public function verify(string $token): ?TokenClaims;
}
