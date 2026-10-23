<?php

declare(strict_types=1);

namespace Library\Application\ReadModels;

use Library\Domain\Model\Member;

/** Socio con el conteo de libros sin devolver (api-contract.md §3.5). */
final class MemberView
{
    public function __construct(public readonly Member $member, public readonly int $activeLoans)
    {
    }
}
