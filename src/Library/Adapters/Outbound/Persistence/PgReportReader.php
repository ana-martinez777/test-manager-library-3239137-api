<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\ReportReader;
use Library\Application\ReadModels\MostBorrowedRow;
use Library\Domain\ValueObjects\DateRange;
use PDO;

/**
 * ADR-004: una consulta agregada que agrupa por los valores CONGELADOS de la línea
 * (book_id, book_title, category_name). No lee el catálogo vivo ni expone socios o usuarios.
 */
final class PgReportReader implements ReportReader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function mostBorrowed(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT li.book_id, li.book_title, li.category_name, count(*) AS times_borrowed
               FROM library.loan_item li
               JOIN library.loan l ON l.id = li.loan_id
              WHERE l.loaned_at >= :from AND l.loaned_at < :to
              GROUP BY li.book_id, li.book_title, li.category_name
              ORDER BY times_borrowed DESC, li.book_title ASC'
        );
        $stmt->execute([
            'from' => PgSupport::toDb($range->startInclusive()),
            'to' => PgSupport::toDb($range->endExclusive()),
        ]);

        return array_map(
            static fn (array $r) => new MostBorrowedRow($r['book_id'], $r['book_title'], $r['category_name'], (int) $r['times_borrowed']),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
