<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoAthleteRepository implements AthleteRepositoryInterface
{
    use BuildsListing;

    private const UPDATABLE = [
        'name', 'birth_date', 'guardian_id', 'has_health_condition', 'health_condition',
        'status', 'enrollment_date', 'notes',
    ];

    /** Ordenações permitidas (chave pública => SQL). */
    private const SORTS = [
        'name' => 'a.name',
        'birth_date' => 'a.birth_date',
        'status' => 'a.status',
        'enrollment_date' => 'a.enrollment_date',
        'guardian' => 'g.name',
        'plan' => 'p.name',
    ];

    private const BASE_FROM = 'FROM athletes a
        JOIN guardians g ON g.id = a.guardian_id
        LEFT JOIN athlete_plans ap ON ap.athlete_id = a.id AND ap.end_date IS NULL
        LEFT JOIN plans p ON p.id = ap.plan_id';

    public function __construct(private Connection $db)
    {
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$whereSql, $params] = $this->where($filters);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) ' . self::BASE_FROM . " WHERE {$whereSql}", $params);
        $order = $this->orderBy($filters['sort'] ?? null, $filters['order'] ?? null, self::SORTS, 'name');

        $rows = $this->db->fetchAll(
            'SELECT a.id, a.name, a.birth_date, a.status, a.has_health_condition, a.health_condition, a.enrollment_date,
                    g.id AS guardian_id, g.name AS guardian_name,
                    ap.plan_id, p.name AS plan_name, p.days_per_week, p.monthly_fee, ap.discount_type, ap.discount_value '
            . self::BASE_FROM . " WHERE {$whereSql} ORDER BY {$order}, a.id LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $positions = $this->positionsFor($ids);
        $phones = $this->phonesForGuardians(array_values(array_unique(array_map(static fn ($r) => (int) $r['guardian_id'], $rows))));

        return [
            'items' => array_map(fn (array $r) => $this->map($r, $positions, $phones), $rows),
            'total' => $total,
        ];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT a.id, a.name, a.birth_date, a.status, a.has_health_condition, a.health_condition, a.enrollment_date, a.notes,
                    a.created_at, a.updated_at,
                    g.id AS guardian_id, g.name AS guardian_name, g.cpf AS guardian_cpf, g.email AS guardian_email,
                    ap.plan_id, p.name AS plan_name, p.days_per_week, p.monthly_fee, ap.discount_type, ap.discount_value,
                    ap.start_date AS plan_start_date '
            . self::BASE_FROM . ' WHERE a.id = ? AND a.deleted_at IS NULL',
            [$id],
        );
        if ($row === null) {
            return null;
        }

        $athlete = $this->map($row, $this->positionsFor([$id]), $this->phonesForGuardians([(int) $row['guardian_id']]));
        $athlete['notes'] = $row['notes'];
        $athlete['created_at'] = $row['created_at'];
        $athlete['updated_at'] = $row['updated_at'];
        $athlete['guardian']['cpf'] = $row['guardian_cpf'];
        $athlete['guardian']['email'] = $row['guardian_email'];
        if ($athlete['plan'] !== null) {
            $athlete['plan']['start_date'] = $row['plan_start_date'];
        }
        $athlete['siblings'] = array_map(
            static fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'status' => $s['status']],
            $this->db->fetchAll(
                'SELECT id, name, status FROM athletes WHERE guardian_id = ? AND id <> ? AND deleted_at IS NULL ORDER BY name',
                [(int) $row['guardian_id'], $id],
            ),
        );

        return $athlete;
    }

    public function exists(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM athletes WHERE id = ? AND deleted_at IS NULL', [$id]) !== null;
    }

    public function guardianIdOf(int $athleteId): ?int
    {
        $id = $this->db->fetchValue('SELECT guardian_id FROM athletes WHERE id = ? AND deleted_at IS NULL', [$athleteId]);

        return $id === null ? null : (int) $id;
    }

    public function byGuardian(int $guardianId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'birth_date' => $r['birth_date'],
            'status' => $r['status'],
        ], $this->db->fetchAll(
            "SELECT id, name, birth_date, status FROM athletes WHERE guardian_id = ? AND deleted_at IS NULL AND status <> 'inativo' ORDER BY name",
            [$guardianId],
        ));
    }

    public function findIdByNameAndBirth(string $name, ?string $birthDate): ?int
    {
        $id = $birthDate === null
            ? $this->db->fetchValue('SELECT id FROM athletes WHERE name = ? AND deleted_at IS NULL LIMIT 1', [trim($name)])
            : $this->db->fetchValue(
                'SELECT id FROM athletes WHERE name = ? AND (birth_date = ? OR birth_date IS NULL) AND deleted_at IS NULL LIMIT 1',
                [trim($name), $birthDate],
            );

        return $id === null ? null : (int) $id;
    }

    public function create(array $data): int
    {
        return $this->db->insert('athletes', $this->filter($data));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('athletes', $this->filter($data), ['id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE athletes SET deleted_at = NOW(), status = 'inativo' WHERE id = ? AND deleted_at IS NULL",
            [$id],
        );
    }

    public function syncPositions(int $id, array $positionIds): void
    {
        $this->db->execute('DELETE FROM athlete_position WHERE athlete_id = ?', [$id]);
        foreach (array_values(array_unique($positionIds)) as $positionId) {
            $this->db->insert('athlete_position', ['athlete_id' => $id, 'position_id' => $positionId]);
        }
    }

    public function counts(): array
    {
        $row = $this->db->fetchOne(
            "SELECT SUM(status = 'ativo') AS active, SUM(status <> 'ativo') AS inactive, COUNT(*) AS total
               FROM athletes WHERE deleted_at IS NULL",
        ) ?? [];

        return ['active' => (int) ($row['active'] ?? 0), 'inactive' => (int) ($row['inactive'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }

    public function birthdaysInMonth(int $month): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, birth_date FROM athletes
              WHERE deleted_at IS NULL AND status = 'ativo' AND MONTH(birth_date) = ?
              ORDER BY DAY(birth_date), name",
            [$month],
        );
    }

    /**
     * @param array<string, mixed> $f
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(array $f): array
    {
        $where = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($f['search'])) {
            $term = (string) $f['search'];
            $digits = (string) preg_replace('/\D/', '', $term);
            // Busca por telefone só quando o termo parece um número (≥ 4 dígitos).
            if (strlen($digits) < 4) {
                $digits = '';
            }
            $where[] = '(a.name LIKE :s1 OR g.name LIKE :s2'
                . ($digits !== '' ? ' OR EXISTS (SELECT 1 FROM guardian_phones gp WHERE gp.guardian_id = g.id AND gp.phone LIKE :s3)' : '')
                . ')';
            $params['s1'] = $this->like($term);
            $params['s2'] = $this->like($term);
            if ($digits !== '') {
                $params['s3'] = $this->like($digits);
            }
        }
        if (!empty($f['status'])) {
            $where[] = 'a.status = :status';
            $params['status'] = $f['status'];
        }
        if (!empty($f['plan_id'])) {
            $where[] = 'ap.plan_id = :plan_id';
            $params['plan_id'] = (int) $f['plan_id'];
        }
        if (!empty($f['birth_year'])) {
            $where[] = 'YEAR(a.birth_date) = :birth_year';
            $params['birth_year'] = (int) $f['birth_year'];
        }
        if (!empty($f['guardian_id'])) {
            $where[] = 'a.guardian_id = :guardian_id';
            $params['guardian_id'] = (int) $f['guardian_id'];
        }
        if (isset($f['health']) && $f['health'] !== null) {
            $where[] = 'a.has_health_condition = :health';
            $params['health'] = (int) $f['health'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::UPDATABLE));
        if (array_key_exists('has_health_condition', $data)) {
            $data['has_health_condition'] = (int) $data['has_health_condition'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $r
     * @param array<int, list<array<string, mixed>>> $positions
     * @param array<int, list<array<string, mixed>>> $phones
     * @return array<string, mixed>
     */
    private function map(array $r, array $positions, array $phones): array
    {
        $id = (int) $r['id'];
        $guardianId = (int) $r['guardian_id'];

        return [
            'id' => $id,
            'name' => $r['name'],
            'birth_date' => $r['birth_date'],
            'status' => $r['status'],
            'has_health_condition' => (bool) $r['has_health_condition'],
            'health_condition' => $r['health_condition'],
            'enrollment_date' => $r['enrollment_date'],
            'positions' => $positions[$id] ?? [],
            'guardian' => [
                'id' => $guardianId,
                'name' => $r['guardian_name'],
                'phones' => $phones[$guardianId] ?? [],
            ],
            'plan' => $r['plan_id'] === null ? null : [
                'id' => (int) $r['plan_id'],
                'name' => $r['plan_name'],
                'days_per_week' => (int) $r['days_per_week'],
                'monthly_fee' => (string) $r['monthly_fee'],
                'discount_type' => $r['discount_type'],
                'discount_value' => (string) $r['discount_value'],
            ],
        ];
    }

    /**
     * @param list<int> $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function positionsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $result = [];
        foreach ($this->db->fetchAll(
            "SELECT ap.athlete_id, p.id, p.name, p.short_name FROM athlete_position ap
               JOIN positions p ON p.id = ap.position_id WHERE ap.athlete_id IN ({$in}) ORDER BY p.sort_order",
            $ids,
        ) as $r) {
            $result[(int) $r['athlete_id']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'short_name' => $r['short_name']];
        }

        return $result;
    }

    /**
     * @param list<int> $guardianIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function phonesForGuardians(array $guardianIds): array
    {
        if ($guardianIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($guardianIds), '?'));
        $result = [];
        foreach ($this->db->fetchAll(
            "SELECT guardian_id, phone, is_whatsapp, label FROM guardian_phones WHERE guardian_id IN ({$in}) ORDER BY id",
            $guardianIds,
        ) as $r) {
            $result[(int) $r['guardian_id']][] = ['phone' => $r['phone'], 'is_whatsapp' => (bool) $r['is_whatsapp'], 'label' => $r['label']];
        }

        return $result;
    }
}
