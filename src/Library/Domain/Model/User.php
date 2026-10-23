<?php

declare(strict_types=1);

namespace Library\Domain\Model;

use Library\Domain\Errors\ValidationFailed;
use Library\Domain\ValueObjects\Text;

/** Usuario interno con rol admin o librarian (spec.md §1, HU-02). */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';

    private function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $role,
    ) {
    }

    /** CA-02.2: minúsculas y recortado. */
    public static function normalizeUsername(string $raw): string
    {
        return mb_strtolower(Text::required($raw, 'username', 120));
    }

    /** CA-02.1 y CA-02.3: por la API solo se crea el rol librarian; pedir admin es 422. */
    public static function createLibrarian(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== self::ROLE_LIBRARIAN) {
            throw new ValidationFailed(
                'Solo se pueden crear usuarios con el rol librarian.',
                [['field' => 'role', 'issue' => 'solo admite librarian']]
            );
        }

        return new self($id, self::normalizeUsername($username), $passwordHash, self::ROLE_LIBRARIAN);
    }

    /** El admin solo lo crea el arranque desde variables de entorno (spec.md §1, plan D-10). */
    public static function createAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, self::normalizeUsername($username), $passwordHash, self::ROLE_ADMIN);
    }

    public static function rehydrate(string $id, string $username, string $passwordHash, string $role): self
    {
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_LIBRARIAN], true)) {
            throw new \UnexpectedValueException('Rol de usuario desconocido.');
        }

        return new self($id, $username, $passwordHash, $role);
    }
}
