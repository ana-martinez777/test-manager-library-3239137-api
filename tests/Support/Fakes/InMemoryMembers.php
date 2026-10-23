<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Library\Application\Errors\DocumentTaken;
use Library\Application\Ports\MemberRepository;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Member;

final class InMemoryMembers implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];

    public function find(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function existsDocument(string $documentNumber): bool
    {
        foreach ($this->members as $m) {
            if ($m->documentNumber->value === $documentNumber) {
                return true;
            }
        }

        return false;
    }

    public function add(Member $member): void
    {
        if ($this->existsDocument($member->documentNumber->value)) {
            throw new DocumentTaken();
        }
        $this->members[$member->id] = $member;
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $found = array_values(array_filter(
            $this->members,
            static fn (Member $m) => $text === null || stripos($m->fullName, $text) !== false || stripos($m->documentNumber->value, $text) !== false
        ));

        return new Page(array_slice($found, ($page - 1) * $size, $size), $page, $size, count($found));
    }
}
