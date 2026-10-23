<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas contra PostgreSQL real (plan §3, nivel Integración). Cada prueba corre dentro de una
 * transacción que se deshace al final: no deja datos en la base.
 * Requiere la base migrada y las variables DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.
 */
abstract class PostgresTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $database = getenv('DB_DATABASE') ?: '';
        if ($database === '' || $database === ':memory:') {
            $this->markTestSkipped('Sin PostgreSQL configurado (DB_DATABASE).');
        }
        try {
            $this->pdo = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '5432', $database),
                getenv('DB_USERNAME') ?: '',
                getenv('DB_PASSWORD') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (\PDOException $e) {
            $this->markTestSkipped('No se pudo conectar a PostgreSQL: ' . $e->getMessage());
        }
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
