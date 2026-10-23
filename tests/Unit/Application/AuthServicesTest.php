<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Library\Application\Errors\InvalidCredentials;
use Library\Application\Errors\UsernameTaken;
use Library\Application\Services\Auth\BootstrapAdminService;
use Library\Application\Services\Auth\CreateLibrarianService;
use Library\Application\Services\Auth\LoginService;
use Library\Domain\Errors\ValidationFailed;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakeTokens;
use Tests\Support\Fakes\InMemoryUsers;
use Tests\Support\Fakes\PlainHasher;
use Tests\Support\Fakes\SequentialIds;

/** T-07: HU-01 y HU-02 contra fakes. */
final class AuthServicesTest extends TestCase
{
    private InMemoryUsers $users;
    private PlainHasher $hasher;
    private SequentialIds $ids;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
        $this->hasher = new PlainHasher();
        $this->ids = new SequentialIds();
    }

    public function test_bootstrap_creates_admin_once(): void
    {
        $service = new BootstrapAdminService($this->users, $this->hasher, $this->ids);
        $this->assertTrue($service->ensureAdmin('Admin', 'secreto'));
        $this->assertFalse($service->ensureAdmin('admin', 'otra'));
        $this->assertSame('admin', $this->users->findByUsername('admin')->role);
        $this->assertNotSame('secreto', $this->users->findByUsername('admin')->passwordHash);
    }

    public function test_login_returns_token_and_role(): void
    {
        (new BootstrapAdminService($this->users, $this->hasher, $this->ids))->ensureAdmin('admin', 'secreto');
        $result = (new LoginService($this->users, $this->hasher, new FakeTokens()))->login(' ADMIN ', 'secreto');

        $this->assertSame('admin', $result->role);
        $this->assertSame(28800, $result->token->expiresIn);
    }

    public function test_wrong_user_and_wrong_password_give_the_same_error(): void
    {
        (new BootstrapAdminService($this->users, $this->hasher, $this->ids))->ensureAdmin('admin', 'secreto');
        $login = new LoginService($this->users, $this->hasher, new FakeTokens());

        $messages = [];
        foreach ([['nadie', 'secreto'], ['admin', 'mala']] as [$user, $password]) {
            try {
                $login->login($user, $password);
                $this->fail('Debió fallar');
            } catch (InvalidCredentials $e) {
                $messages[] = $e->getMessage();
            }
        }
        $this->assertSame($messages[0], $messages[1], 'CA-01.2: el mensaje no revela qué falló');
    }

    public function test_admin_creates_librarian_with_normalized_unique_name(): void
    {
        $service = new CreateLibrarianService($this->users, $this->hasher, $this->ids);
        $user = $service->create('Ana ', 'clave', 'librarian');
        $this->assertSame('ana', $user->username);

        $this->expectException(UsernameTaken::class);
        $service->create('ANA', 'otra', 'librarian');
    }

    public function test_requesting_admin_role_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        (new CreateLibrarianService($this->users, $this->hasher, $this->ids))->create('jefe', 'clave', 'admin');
    }
}
