<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Errors\DocumentTaken;
use Library\Application\Ports\MemberRepository;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Member;
use PDO;
use PDOException;

final class PgMemberRepository implements MemberRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $id): ?Member
    {
        $stmt = $this->pdo->prepare('SELECT id, full_name, document_number FROM library.member WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::map($row);
    }

    public function existsDocument(string $documentNumber): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM library.member WHERE document_number = :doc');
        $stmt->execute(['doc' => $documentNumber]);

        return $stmt->fetchColumn() !== false;
    }

    public function add(Member $member): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO library.member (id, full_name, document_number) VALUES (:id, :name, :doc)');
        try {
            $stmt->execute(['id' => $member->id, 'name' => $member->fullName, 'doc' => $member->documentNumber->value]);
        } catch (PDOException $e) {
            if (PgSupport::isUniqueViolation($e, 'ux_member_document_number')) {
                throw new DocumentTaken();
            }
            throw $e;
        }
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $whereSql = 'TRUE';
        $params = [];
        if ($text !== null) {
            $whereSql = '(full_name ILIKE :q OR document_number ILIKE :q)';
            $params['q'] = PgSupport::likePattern($text);
        }
        $count = $this->pdo->prepare("SELECT count(*) FROM library.member WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT id, full_name, document_number FROM library.member WHERE {$whereSql} ORDER BY full_name, id LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $size, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $size, PDO::PARAM_INT);
        $stmt->execute();

        return new Page(array_map(self::map(...), $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $size, $total);
    }

    private static function map(array $row): Member
    {
        return Member::rehydrate($row['id'], $row['full_name'], $row['document_number']);
    }
}
