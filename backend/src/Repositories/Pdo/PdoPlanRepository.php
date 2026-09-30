<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\PlanRepositoryInterface;

final class PdoPlanRepository implements PlanRepositoryInterface
{
    private const UPDATABLE = ['name', 'description', 'days_per_week', 'monthly_fee', 'active'];

    public function __construct(private Connection $db)
    {
    }

    public function all(bool $onlyActive = false): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.name, p.description, p.days_per_week, p.monthly_fee, p.active,
                    (SELECT COUNT(*) FROM athlete_plans ap JOIN athletes a ON a.id = ap.athlete_id
                      WHERE ap.plan_id = p.id AND ap.end_date IS NULL AND a.deleted_at IS NULL AND a.status = \'ativo\') AS athletes_count
               FROM plans p ' . ($onlyActive ? 'WHERE p.active = 1 ' : '') . 'ORDER BY p.days_per_week, p.name',
        );

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, name, description, days_per_week, monthly_fee, active, 0 AS athletes_count FROM plans WHERE id = ?',
            [$id],
        );

        return $row ? $this->map($row) : null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('plans', $this->filter($data));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('plans', $this->filter($data), ['id' => $id]);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM plans WHERE name = ? AND id <> ?', [$name, $exceptId ?? 0]) !== null;
    }

    public function activeAthletesCount(int $id): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM athlete_plans ap JOIN athletes a ON a.id = ap.athlete_id
              WHERE ap.plan_id = ? AND ap.end_date IS NULL AND a.deleted_at IS NULL',
            [$id],
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::UPDATABLE));
        if (array_key_exists('active', $data)) {
            $data['active'] = (int) $data['active'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'description' => $r['description'],
            'days_per_week' => (int) $r['days_per_week'],
            'monthly_fee' => (string) $r['monthly_fee'],
            'active' => (bool) $r['active'],
            'athletes_count' => (int) $r['athletes_count'],
        ];
    }
}
