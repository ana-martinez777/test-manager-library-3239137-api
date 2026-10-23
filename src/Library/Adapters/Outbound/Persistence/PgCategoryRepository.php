<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\CategoryRepository;
use Library\Domain\Model\Category;
use PDO;

final class PgCategoryRepository implements CategoryRepository
{
    /** @var list<Category>|null */
    private ?array $cache = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $rows = $this->pdo->query('SELECT id, name FROM library.category ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
            $this->cache = array_map(static fn (array $r) => new Category($r['id'], $r['name']), $rows);
        }

        return $this->cache;
    }

    public function find(string $id): ?Category
    {
        foreach ($this->all() as $category) {
            if ($category->id === strtolower($id)) {
                return $category;
            }
        }

        return null;
    }
}
