<?php

declare(strict_types=1);

namespace Library\Bootstrap\Composition;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Library\Adapters\Outbound\Persistence\PgBookRepository;
use Library\Adapters\Outbound\Persistence\PgCategoryRepository;
use Library\Adapters\Outbound\Persistence\PgLoanRepository;
use Library\Adapters\Outbound\Persistence\PgMemberRepository;
use Library\Adapters\Outbound\Persistence\PgReportReader;
use Library\Adapters\Outbound\Persistence\PgUserRepository;
use Library\Adapters\Outbound\Security\BcryptPasswordHasher;
use Library\Adapters\Outbound\Security\JwtTokenService;
use Library\Adapters\Outbound\Storage\LocalDiskCoverStorage;
use Library\Adapters\Outbound\System\RandomUuidGenerator;
use Library\Adapters\Outbound\System\SystemClock;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\CoverStorage;
use Library\Application\Ports\IdGenerator;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\ReportReader;
use Library\Application\Ports\TokenIssuer;
use Library\Application\Ports\TokenVerifier;
use Library\Application\Ports\UserRepository;
use Library\Bootstrap\Console\BootstrapAdminCommand;
use PDO;

/**
 * Artículo III: el ÚNICO punto de composición. Aquí, y solo aquí, cada puerto se encuentra con
 * su adaptador. Cambiar un adaptador (por ejemplo, disco → S3) es cambiar una línea de este archivo.
 * Los casos de uso no se registran: el contenedor de Laravel los construye con estos puertos.
 */
final class PortBindings extends ServiceProvider
{
    public function register(): void
    {
        $pdo = static fn (Application $app): PDO => $app['db']->connection()->getPdo();

        // Persistencia (PostgreSQL 16, esquema library).
        $this->app->singleton(CategoryRepository::class, static fn ($app) => new PgCategoryRepository($pdo($app)));
        $this->app->singleton(BookRepository::class, static fn ($app) => new PgBookRepository($pdo($app)));
        $this->app->singleton(MemberRepository::class, static fn ($app) => new PgMemberRepository($pdo($app)));
        $this->app->singleton(UserRepository::class, static fn ($app) => new PgUserRepository($pdo($app)));
        $this->app->singleton(LoanRepository::class, static fn ($app) => new PgLoanRepository($pdo($app)));
        $this->app->singleton(ReportReader::class, static fn ($app) => new PgReportReader($pdo($app)));

        // Sistema.
        $this->app->singleton(Clock::class, static fn () => new SystemClock());
        $this->app->singleton(IdGenerator::class, static fn () => new RandomUuidGenerator());

        // Seguridad: secretos desde el entorno (artículo IX).
        $this->app->singleton(PasswordHasher::class, static fn () => new BcryptPasswordHasher((int) config('library.bcrypt_cost')));
        $this->app->singleton(JwtTokenService::class, static fn ($app) => new JwtTokenService(
            (string) config('library.jwt_secret'),
            $app->make(Clock::class),
            (int) config('library.jwt_ttl_seconds'),
        ));
        $this->app->singleton(TokenIssuer::class, static fn ($app) => $app->make(JwtTokenService::class));
        $this->app->singleton(TokenVerifier::class, static fn ($app) => $app->make(JwtTokenService::class));

        // Portadas en disco local.
        $this->app->singleton(CoverStorage::class, static fn () => new LocalDiskCoverStorage((string) config('library.covers_dir')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdminCommand::class]);
        }
    }
}
