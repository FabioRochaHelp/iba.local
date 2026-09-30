<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\PositionRepositoryInterface;

final class PdoPositionRepository implements PositionRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function all(): array
    {
        return array_map(
            static fn (array $r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'short_name' => $r['short_name']],
            $this->db->fetchAll('SELECT id, name, short_name FROM positions ORDER BY sort_order, name'),
        );
    }

    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return array_map('intval', array_column(
            $this->db->fetchAll("SELECT id FROM positions WHERE id IN ({$placeholders})", array_values($ids)),
            'id',
        ));
    }
}
