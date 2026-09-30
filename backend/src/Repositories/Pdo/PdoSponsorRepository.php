<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\SponsorRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoSponsorRepository implements SponsorRepositoryInterface
{
    use BuildsListing;

    private const COLUMNS = ['name', 'document', 'contact', 'phone', 'email', 'notes', 'active'];

    public function __construct(private Connection $db)
    {
    }

    public function all(?string $search = null): array
    {
        $params = [];
        $where = '1 = 1';
        if ($search !== null && $search !== '') {
            $where = '(s.name LIKE :s1 OR s.contact LIKE :s2)';
            $params = ['s1' => $this->like($search), 's2' => $this->like($search)];
        }

        return array_map([$this, 'map'], $this->db->fetchAll(
            "SELECT s.*, COALESCE(SUM(sp.amount), 0) AS total_amount, COUNT(sp.id) AS entries,
                    MAX(sp.received_at) AS last_received_at
               FROM sponsors s LEFT JOIN sponsorships sp ON sp.sponsor_id = s.id
              WHERE {$where} GROUP BY s.id ORDER BY s.active DESC, s.name",
            $params,
        ));
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT s.*, COALESCE(SUM(sp.amount), 0) AS total_amount, COUNT(sp.id) AS entries, MAX(sp.received_at) AS last_received_at
               FROM sponsors s LEFT JOIN sponsorships sp ON sp.sponsor_id = s.id WHERE s.id = ? GROUP BY s.id',
            [$id],
        );

        return $row ? $this->map($row) : null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('sponsors', $this->filter($data));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('sponsors', $this->filter($data), ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM sponsors WHERE id = ?', [$id]);
    }

    public function sponsorshipsCount(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM sponsorships WHERE sponsor_id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
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
            'document' => $r['document'],
            'contact' => $r['contact'],
            'phone' => $r['phone'],
            'email' => $r['email'],
            'notes' => $r['notes'],
            'active' => (bool) $r['active'],
            'total_amount' => number_format((float) $r['total_amount'], 2, '.', ''),
            'entries' => (int) $r['entries'],
            'last_received_at' => $r['last_received_at'],
        ];
    }
}
