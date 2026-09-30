<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\GuardianRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoGuardianRepository implements GuardianRepositoryInterface
{
    use BuildsListing;

    private const UPDATABLE = ['name', 'cpf', 'email', 'notes'];

    public function __construct(private Connection $db)
    {
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['search'])) {
            $term = (string) $filters['search'];
            $where[] = '(g.name LIKE :s1 OR EXISTS (SELECT 1 FROM guardian_phones gp WHERE gp.guardian_id = g.id AND gp.phone LIKE :s2)
                        OR EXISTS (SELECT 1 FROM athletes a WHERE a.guardian_id = g.id AND a.deleted_at IS NULL AND a.name LIKE :s3))';
            $params['s1'] = $this->like($term);
            $digits = preg_replace('/\D/', '', $term);
            $params['s2'] = strlen((string) $digits) >= 4 ? $this->like((string) $digits) : '__nenhum__';
            $params['s3'] = $this->like($term);
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM guardians g WHERE {$whereSql}", $params);
        $rows = $this->db->fetchAll(
            "SELECT g.id, g.name, g.cpf, g.email,
                    (SELECT COUNT(*) FROM athletes a WHERE a.guardian_id = g.id AND a.deleted_at IS NULL) AS athletes_count
               FROM guardians g WHERE {$whereSql}
              ORDER BY g.name LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        $phones = $this->phonesFor(array_map(static fn ($r) => (int) $r['id'], $rows));
        $athletes = $this->athletesFor(array_map(static fn ($r) => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['athletes_count'] = (int) $row['athletes_count'];
            $row['phones'] = $phones[$row['id']] ?? [];
            $row['athletes'] = $athletes[$row['id']] ?? [];
        }

        return ['items' => $rows, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT id, name, cpf, email, notes, created_at, updated_at FROM guardians WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['phones'] = $this->phonesFor([$id])[$id] ?? [];
        $row['athletes'] = $this->athletesFor([$id])[$id] ?? [];

        return $row;
    }

    public function findIdByPhone(string $phone): ?int
    {
        $id = $this->db->fetchValue('SELECT guardian_id FROM guardian_phones WHERE phone = ? ORDER BY guardian_id LIMIT 1', [$phone]);

        return $id === null ? null : (int) $id;
    }

    public function findIdByName(string $name): ?int
    {
        // Collation utf8mb4_unicode_ci: comparação sem diferenciar maiúsculas/acentos.
        $id = $this->db->fetchValue('SELECT id FROM guardians WHERE name = ? ORDER BY id LIMIT 1', [trim($name)]);

        return $id === null ? null : (int) $id;
    }

    public function create(array $data): int
    {
        return $this->db->insert('guardians', array_intersect_key($data, array_flip(self::UPDATABLE)));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('guardians', array_intersect_key($data, array_flip(self::UPDATABLE)), ['id' => $id]);
    }

    public function syncPhones(int $id, array $phones): void
    {
        $this->db->execute('DELETE FROM guardian_phones WHERE guardian_id = ?', [$id]);
        foreach ($phones as $phone) {
            $this->db->insert('guardian_phones', [
                'guardian_id' => $id,
                'phone' => $phone['phone'],
                'is_whatsapp' => (int) ($phone['is_whatsapp'] ?? true),
                'label' => $phone['label'] ?? null,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM guardians WHERE id = ?', [$id]);
    }

    public function athletesCount(int $id): int
    {
        // Inclui excluídos (soft delete): a FK impede apagar o responsável.
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM athletes WHERE guardian_id = ?', [$id]);
    }

    public function cpfExists(string $cpf, ?int $exceptId = null): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM guardians WHERE cpf = ? AND id <> ?', [$cpf, $exceptId ?? 0]) !== null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function phonesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $result = [];
        foreach ($this->db->fetchAll("SELECT guardian_id, phone, is_whatsapp, label FROM guardian_phones WHERE guardian_id IN ({$in}) ORDER BY id", $ids) as $r) {
            $result[(int) $r['guardian_id']][] = ['phone' => $r['phone'], 'is_whatsapp' => (bool) $r['is_whatsapp'], 'label' => $r['label']];
        }

        return $result;
    }

    /**
     * @param list<int> $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function athletesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $result = [];
        foreach ($this->db->fetchAll("SELECT id, guardian_id, name, status FROM athletes WHERE guardian_id IN ({$in}) AND deleted_at IS NULL ORDER BY name", $ids) as $r) {
            $result[(int) $r['guardian_id']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'status' => $r['status']];
        }

        return $result;
    }
}
