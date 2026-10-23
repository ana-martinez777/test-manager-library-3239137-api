<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Errors\UsernameTaken;
use Library\Application\Ports\UserRepository;
use Library\Domain\Model\User;
use PDO;
use PDOException;

final class PgUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, username, password_hash, role FROM library."user" WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::rehydrate($row['id'], $row['username'], $row['password_hash'], $row['role']);
    }

    public function add(User $user): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO library."user" (id, username, password_hash, role) VALUES (:id, :username, :hash, :role)');
        try {
            $stmt->execute(['id' => $user->id, 'username' => $user->username, 'hash' => $user->passwordHash, 'role' => $user->role]);
        } catch (PDOException $e) {
            if (PgSupport::isUniqueViolation($e, 'ux_user_username')) {
                throw new UsernameTaken();
            }
            throw $e;
        }
    }
}
