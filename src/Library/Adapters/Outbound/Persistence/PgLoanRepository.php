<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\Ports\LoanRepository;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\ReadModels\OverdueLine;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Loan;
use Library\Domain\Model\LoanItem;
use PDO;
use PDOException;

final class PgLoanRepository implements LoanRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $id): ?Loan
    {
        $stmt = $this->pdo->prepare('SELECT id, member_id, loaned_by_user_id, loaned_at FROM library.loan WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrate([$row])[0];
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT count(*) FROM library.loan_item li JOIN library.loan l ON l.id = li.loan_id
              WHERE l.member_id = :member_id AND li.returned_at IS NULL'
        );
        $stmt->execute(['member_id' => $memberId]);

        return (int) $stmt->fetchColumn();
    }

    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM library.loan_item li JOIN library.loan l ON l.id = li.loan_id
              WHERE l.member_id = :member_id AND li.returned_at IS NULL AND li.due_at < :now LIMIT 1'
        );
        $stmt->execute(['member_id' => $memberId, 'now' => PgSupport::toDb($now)]);

        return $stmt->fetchColumn() !== false;
    }

    public function bookHasOpenLoans(string $bookId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM library.loan_item WHERE book_id = :book_id AND returned_at IS NULL LIMIT 1');
        $stmt->execute(['book_id' => $bookId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Una transacción: descuenta cada libro con un UPDATE condicionado (solo si queda al menos
     * un ejemplar) y luego inserta préstamo y líneas. Si algún descuento no afecta filas, otro
     * préstamo ganó el último ejemplar: se deshace todo (RNF-2, ADR-002).
     */
    public function add(Loan $loan, array $books): void
    {
        PgSupport::transaction($this->pdo, function () use ($loan): void {
            $withdraw = $this->pdo->prepare(
                'UPDATE library.book SET available_copies = available_copies - 1
                  WHERE id = :id AND deleted_at IS NULL AND available_copies >= 1'
            );
            foreach ($loan->items() as $item) {
                try {
                    $withdraw->execute(['id' => $item->bookId]);
                } catch (PDOException $e) {
                    if (PgSupport::isCheckViolation($e)) {
                        throw new ConcurrencyConflict('Sin ejemplares en el motor.', 0, $e);
                    }
                    throw $e;
                }
                if ($withdraw->rowCount() !== 1) {
                    throw new ConcurrencyConflict('Otro préstamo tomó el último ejemplar.');
                }
            }

            $this->pdo->prepare(
                'INSERT INTO library.loan (id, member_id, loaned_by_user_id, loaned_at) VALUES (:id, :member_id, :user_id, :loaned_at)'
            )->execute([
                'id' => $loan->id,
                'member_id' => $loan->memberId,
                'user_id' => $loan->loanedByUserId,
                'loaned_at' => PgSupport::toDb($loan->loanedAt),
            ]);

            $insertItem = $this->pdo->prepare(
                'INSERT INTO library.loan_item (id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at)
                 VALUES (:id, :loan_id, :book_id, :book_title, :isbn, :category_name, :due_at, NULL)'
            );
            foreach ($loan->items() as $item) {
                $insertItem->execute([
                    'id' => $item->id,
                    'loan_id' => $loan->id,
                    'book_id' => $item->bookId,
                    'book_title' => $item->bookTitle,
                    'isbn' => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at' => PgSupport::toDb($item->dueAt),
                ]);
            }
        });
    }

    /**
     * Una transacción: marca la línea solo si seguía sin devolver y suma el ejemplar.
     * Si la línea ya estaba devuelta, nada cambia (CA-07.2: nunca suma dos veces).
     */
    public function saveReturn(Loan $loan, string $itemId): void
    {
        $item = $loan->item($itemId) ?? throw new \InvalidArgumentException('Línea inexistente.');
        $returnedAt = $item->returnedAt() ?? throw new \InvalidArgumentException('La línea no está devuelta en el dominio.');

        PgSupport::transaction($this->pdo, function () use ($loan, $item, $returnedAt): void {
            $mark = $this->pdo->prepare(
                'UPDATE library.loan_item SET returned_at = :returned_at
                  WHERE id = :id AND loan_id = :loan_id AND returned_at IS NULL'
            );
            $mark->execute(['returned_at' => PgSupport::toDb($returnedAt), 'id' => $item->id, 'loan_id' => $loan->id]);
            if ($mark->rowCount() !== 1) {
                throw new ConcurrencyConflict('La línea ya estaba devuelta.');
            }

            // El CHECK ck_book_available_range es la última barrera: disponibles nunca > total (CA-07.3).
            $this->pdo->prepare('UPDATE library.book SET available_copies = available_copies + 1 WHERE id = :id')
                ->execute(['id' => $item->bookId]);
        });
    }

    public function search(LoanSearchCriteria $criteria, DateTimeImmutable $now, int $page, int $size): Page
    {
        $where = ['TRUE'];
        $params = [];
        if ($criteria->fromInclusive !== null) {
            $where[] = 'l.loaned_at >= :from';
            $params['from'] = PgSupport::toDb($criteria->fromInclusive);
        }
        if ($criteria->toExclusive !== null) {
            $where[] = 'l.loaned_at < :to';
            $params['to'] = PgSupport::toDb($criteria->toExclusive);
        }
        if ($criteria->memberId !== null) {
            $where[] = 'l.member_id = :member_id';
            $params['member_id'] = $criteria->memberId;
        }
        // El estado se calcula, no se almacena (artículo VII): se traduce a condiciones sobre las líneas.
        $pending = 'EXISTS (SELECT 1 FROM library.loan_item p WHERE p.loan_id = l.id AND p.returned_at IS NULL)';
        $late = 'EXISTS (SELECT 1 FROM library.loan_item o WHERE o.loan_id = l.id AND o.returned_at IS NULL AND o.due_at < :now)';
        if ($criteria->status === Loan::STATUS_CLOSED) {
            $where[] = "NOT {$pending}";
        } elseif ($criteria->status === Loan::STATUS_OVERDUE) {
            $where[] = $late;
            $params['now'] = PgSupport::toDb($now);
        } elseif ($criteria->status === Loan::STATUS_OPEN) {
            $where[] = "{$pending} AND NOT {$late}";
            $params['now'] = PgSupport::toDb($now);
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT count(*) FROM library.loan l WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT l.id, l.member_id, l.loaned_by_user_id, l.loaned_at FROM library.loan l
              WHERE {$whereSql} ORDER BY l.loaned_at DESC, l.id LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $size, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $size, PDO::PARAM_INT);
        $stmt->execute();

        return new Page($this->hydrate($stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $size, $total);
    }

    public function overdue(DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT li.loan_id, li.id AS item_id, li.book_title, m.full_name AS member_name, li.due_at
               FROM library.loan_item li
               JOIN library.loan l ON l.id = li.loan_id
               JOIN library.member m ON m.id = l.member_id
              WHERE li.returned_at IS NULL AND li.due_at < :now
              ORDER BY li.due_at ASC, li.id'
        );
        $stmt->execute(['now' => PgSupport::toDb($now)]);

        return array_map(static function (array $r) use ($now): OverdueLine {
            $dueAt = PgSupport::fromDb($r['due_at']);

            return new OverdueLine($r['loan_id'], $r['item_id'], $r['book_title'], $r['member_name'], $dueAt, LoanItem::daysLate($dueAt, $now));
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<array<string, mixed>> $loanRows
     * @return list<Loan>
     */
    private function hydrate(array $loanRows): array
    {
        if ($loanRows === []) {
            return [];
        }
        $ids = array_column($loanRows, 'id');
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at
               FROM library.loan_item WHERE loan_id IN ({$placeholders}) ORDER BY due_at, book_title, id"
        );
        $stmt->execute($ids);
        $itemsByLoan = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itemsByLoan[$r['loan_id']][] = new LoanItem(
                $r['id'],
                $r['book_id'],
                $r['book_title'],
                trim($r['isbn']),
                $r['category_name'],
                PgSupport::fromDb($r['due_at']),
                PgSupport::fromDb($r['returned_at']),
            );
        }

        return array_map(static fn (array $r) => Loan::rehydrate(
            $r['id'],
            $r['member_id'],
            $r['loaned_by_user_id'],
            PgSupport::fromDb($r['loaned_at']),
            $itemsByLoan[$r['id']] ?? [],
        ), $loanRows);
    }
}
