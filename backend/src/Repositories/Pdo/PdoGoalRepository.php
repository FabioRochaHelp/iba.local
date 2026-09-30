<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\GoalRepositoryInterface;

final class PdoGoalRepository implements GoalRepositoryInterface
{
    private const COLUMNS = ['athlete_id', 'title', 'description', 'target_date', 'status', 'achieved_at', 'created_by'];

    public function __construct(private Connection $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert('athlete_goals', array_intersect_key($data, array_flip(self::COLUMNS)));
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, athlete_id, created_by, status FROM athlete_goals WHERE id = ?', [$id]);
    }

    public function forAthlete(int $athleteId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'description' => $r['description'],
            'target_date' => $r['target_date'],
            'status' => $r['status'],
            'achieved_at' => $r['achieved_at'],
            'created_by' => $r['created_by'] !== null ? (int) $r['created_by'] : null,
            'created_by_name' => $r['created_by_name'],
            'created_at' => $r['created_at'],
        ], $this->db->fetchAll(
            "SELECT g.*, u.name AS created_by_name FROM athlete_goals g LEFT JOIN users u ON u.id = g.created_by
              WHERE g.athlete_id = ? ORDER BY FIELD(g.status, 'em_andamento', 'atingida', 'cancelada'), g.target_date IS NULL, g.target_date, g.id DESC",
            [$athleteId],
        ));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('athlete_goals', array_intersect_key($data, array_flip(['title', 'description', 'target_date', 'status', 'achieved_at'])), ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM athlete_goals WHERE id = ?', [$id]);
    }
}
