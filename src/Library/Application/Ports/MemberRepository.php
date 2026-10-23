<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\Page;
use Library\Domain\Model\Member;

interface MemberRepository
{
    public function find(string $id): ?Member;

    public function existsDocument(string $documentNumber): bool;

    /** Lanza DocumentTaken si choca con el índice único. */
    public function add(Member $member): void;

    /** @return Page<Member> */
    public function search(?string $text, int $page, int $size): Page;
}
