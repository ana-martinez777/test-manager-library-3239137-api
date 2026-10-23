<?php

declare(strict_types=1);

namespace Library\Bootstrap\Console;

use Illuminate\Console\Command;
use Library\Application\Services\Auth\BootstrapAdminService;

/**
 * T-17 · Plan D-10: crea el admin inicial desde ADMIN_USERNAME / ADMIN_PASSWORD al arrancar.
 * Idempotente. Nunca imprime la contraseña.
 */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';

    protected $description = 'Crea el administrador inicial desde las variables de entorno, si no existe.';

    public function handle(BootstrapAdminService $service): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');
        if (trim($username) === '' || $password === '') {
            $this->error('Faltan ADMIN_USERNAME o ADMIN_PASSWORD en el entorno.');

            return self::FAILURE;
        }

        $created = $service->ensureAdmin($username, $password);
        $this->info($created ? 'Administrador inicial creado.' : 'El administrador ya existía; no se cambió nada.');

        return self::SUCCESS;
    }
}
