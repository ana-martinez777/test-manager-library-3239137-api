<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

/** Utilidades compartidas por los repositorios PostgreSQL (fechas UTC, transacciones, errores). */
final class PgSupport
{
    public static function toDb(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function fromDb(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    /** Escapa los comodines de LIKE para buscar el texto tal cual. */
    public static function likePattern(string $text): string
    {
        return '%' . addcslashes($text, '%_\\') . '%';
    }

    /**
     * Ejecuta $work en una transacción. Si ya hay una abierta (por ejemplo, en una prueba de
     * integración que la deshace al final), la reutiliza.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function transaction(PDO $pdo, callable $work): mixed
    {
        if ($pdo->inTransaction()) {
            return $work();
        }
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function isUniqueViolation(PDOException $e, string $indexName): bool
    {
        return self::sqlState($e) === '23505' && str_contains($e->getMessage(), $indexName);
    }

    public static function isCheckViolation(PDOException $e): bool
    {
        return self::sqlState($e) === '23514';
    }

    private static function sqlState(PDOException $e): string
    {
        return (string) ($e->errorInfo[0] ?? $e->getCode());
    }
}
