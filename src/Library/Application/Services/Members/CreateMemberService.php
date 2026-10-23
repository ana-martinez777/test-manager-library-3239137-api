<?php

declare(strict_types=1);

namespace Library\Application\Services\Members;

use Library\Application\Errors\DocumentTaken;
use Library\Application\Ports\IdGenerator;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Model\Member;
use Library\Domain\ValueObjects\DocumentNumber;

/** HU-05 · CA-05.1: documento único (409 si se repite). */
final class CreateMemberService
{
    public function __construct(private readonly MemberRepository $members, private readonly IdGenerator $ids)
    {
    }

    public function create(string $fullName, string $documentNumber): Member
    {
        $member = Member::create($this->ids->newId(), $fullName, DocumentNumber::fromString($documentNumber));
        if ($this->members->existsDocument($member->documentNumber->value)) {
            throw new DocumentTaken();
        }
        $this->members->add($member);

        return $member;
    }
}
