<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Library\Domain\Errors\ValidationFailed;
use Library\Domain\Model\Member;
use Library\Domain\Model\User;
use Library\Domain\ValueObjects\DocumentNumber;
use PHPUnit\Framework\TestCase;

/** T-06: normalización de nombre y rol válido. */
final class MemberAndUserTest extends TestCase
{
    public function test_username_is_trimmed_and_lowercased(): void
    {
        $user = User::createLibrarian('u1', 'Ana ', 'hash', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame('librarian', $user->role);
    }

    public function test_admin_role_is_rejected_through_the_api_path(): void
    {
        try {
            User::createLibrarian('u1', 'ana', 'hash', 'admin');
            $this->fail('Debió rechazar el rol admin');
        } catch (ValidationFailed $e) {
            $this->assertSame('role', $e->details()[0]['field']);
        }
    }

    public function test_member_requires_name_and_document(): void
    {
        $member = Member::create('m1', ' Ana Pérez ', DocumentNumber::fromString(' 1075 '));
        $this->assertSame('Ana Pérez', $member->fullName);
        $this->assertSame('1075', $member->documentNumber->value);

        $this->expectException(ValidationFailed::class);
        DocumentNumber::fromString('   ');
    }
}
