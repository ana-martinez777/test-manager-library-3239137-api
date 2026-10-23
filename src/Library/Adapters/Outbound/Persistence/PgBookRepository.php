<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Errors\IsbnTaken;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Book;
use PDO;
use PDOException;

final class PgBookRepository implements BookRepository
{
    private const COLUMNS = 'id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActive(string $id): ?Book
    {
        return $this->one('SELECT ' . self::COLUMNS . ' FROM library.book WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    public function findAny(string $id): ?Book
    {
        return $this->one('SELECT ' . self::COLUMNS . ' FROM library.book WHERE id = :id', ['id' => $id]);
    }

    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . " FROM library.book WHERE deleted_at IS NULL AND id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        $books = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $book = self::map($row);
            $books[$book->id] = $book;
        }

        return $books;
    }

    public function existsActiveIsbn(string $isbn): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM library.book WHERE isbn = :isbn AND deleted_at IS NULL');
        $stmt->execute(['isbn' => $isbn]);

        return $stmt->fetchColumn() !== false;
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($text !== null) {
            $where[] = '(title ILIKE :q OR author ILIKE :q)';
            $params['q'] = PgSupport::likePattern($text);
        }
        if ($categoryId !== null) {
            $where[] = 'category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT count(*) FROM library.book WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . " FROM library.book WHERE {$whereSql} ORDER BY title, id LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $size, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $size, PDO::PARAM_INT);
        $stmt->execute();

        return new Page(array_map(self::map(...), $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $size, $total);
    }

    public function add(Book $book): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO library.book (' . self::COLUMNS . ')
             VALUES (:id, :title, :author, :isbn, :category_id, :total, :available, :cover_key, :deleted_at)'
        );
        try {
            $stmt->execute([
                'id' => $book->id,
                'title' => $book->title(),
                'author' => $book->author(),
                'isbn' => $book->isbn()->value,
                'category_id' => $book->categoryId(),
                'total' => $book->totalCopies(),
                'available' => $book->availableCopies(),
                'cover_key' => $book->coverKey(),
                'deleted_at' => $book->deletedAt() ? PgSupport::toDb($book->deletedAt()) : null,
            ]);
        } catch (PDOException $e) {
            if (PgSupport::isUniqueViolation($e, 'ux_book_isbn_active')) {
                throw new IsbnTaken();
            }
            throw $e;
        }
    }

    /**
     * Ajuste atómico en el motor: total y disponibles cambian en la misma diferencia y la fila
     * solo se actualiza si los disponibles no quedan negativos (ADR-002, sin bloqueo pesimista).
     */
    public function update(Book $book, int $copiesDifference): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE library.book
                SET title = :title, author = :author, category_id = :category_id,
                    total_copies = total_copies + :diff, available_copies = available_copies + :diff
              WHERE id = :id AND deleted_at IS NULL AND available_copies + :diff >= 0'
        );
        try {
            $stmt->execute([
                'title' => $book->title(),
                'author' => $book->author(),
                'category_id' => $book->categoryId(),
                'diff' => $copiesDifference,
                'id' => $book->id,
            ]);
        } catch (PDOException $e) {
            if (PgSupport::isCheckViolation($e)) {
                throw new ConcurrencyConflict('El ajuste de ejemplares viola el rango permitido.', 0, $e);
            }
            throw $e;
        }
        if ($stmt->rowCount() === 0) {
            throw new ConcurrencyConflict('El libro cambió mientras se editaba.');
        }
    }

    public function markDeleted(Book $book): void
    {
        $stmt = $this->pdo->prepare('UPDATE library.book SET deleted_at = :deleted_at WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['deleted_at' => PgSupport::toDb($book->deletedAt() ?? new \DateTimeImmutable()), 'id' => $book->id]);
        if ($stmt->rowCount() === 0) {
            throw new ConcurrencyConflict('El libro ya no estaba activo.');
        }
    }

    public function updateCover(Book $book): void
    {
        $stmt = $this->pdo->prepare('UPDATE library.book SET cover_key = :cover_key WHERE id = :id');
        $stmt->execute(['cover_key' => $book->coverKey(), 'id' => $book->id]);
    }

    private function one(string $sql, array $params): ?Book
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::map($row);
    }

    private static function map(array $row): Book
    {
        return Book::rehydrate(
            $row['id'],
            $row['title'],
            $row['author'],
            trim($row['isbn']),
            $row['category_id'],
            (int) $row['total_copies'],
            (int) $row['available_copies'],
            $row['cover_key'],
            PgSupport::fromDb($row['deleted_at']),
        );
    }
}
