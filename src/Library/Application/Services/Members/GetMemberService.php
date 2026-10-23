<?php

declare(strict_types=1);

namespace Library\Application\Services\Members;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Application\ReadModels\MemberView;

/** GET /members/{id}: detalle con active_loans (api-contract.md §3.5). */
final class GetMemberService
{
    public function __construct(private readonly MemberRepository $members, private readonly LoanRepository $loans)
    {
    }

    public function get(string $id): MemberView
    {
        $member = $this->members->find($id) ?? throw new NotFound('El socio no existe.');

        return new MemberView($member, $this->loans->countUnreturnedByMember($id));
    }
}
