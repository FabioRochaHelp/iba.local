<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\SponsorshipRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoSponsorshipRepository implements SponsorshipRepositoryInterface
{
    use BuildsListing;

    private const COLUMNS = ['sponsor_id', 'athlete_id', 'amount', 'received_at', 'method', 'description', 'created_by'];

    private const SELECT = 'SELECT sp.id, sp.sponsor_id, sp.athlete_id, sp.amount, sp.received_at, sp.method, sp.description, sp.created_at,
            s.name AS sponsor_name, a.name AS athlete_name, u.name AS created_by_name
       FROM sponsorships sp
  LEFT JOIN sponsors s ON s.id = sp.sponsor_id
  LEFT JOIN athletes a ON a.id = sp.athlete_id
  LEFT JOIN users u ON u.id = sp.created_by';

    public function __construct(private Connection $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert('sponsorships', array_intersect_key($data, array_flip(self::COLUMNS)));
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE sp.id = ?', [$id]);

        return $row ? $this->map($row) : null;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM sponsorships WHERE id = ?', [$id]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['from'])) {
            $where[] = 'sp.received_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'sp.received_at <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['sponsor_id'])) {
            $where[] = 'sp.sponsor_id = :sponsor_id';
            $params['sponsor_id'] = (int) $filters['sponsor_id'];
        }
        if (!empty($filters['athlete_id'])) {
            $where[] = 'sp.athlete_id = :athlete_id';
            $params['athlete_id'] = (int) $filters['athlete_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(s.name LIKE :s1 OR a.name LIKE :s2 OR sp.description LIKE :s3)';
            $params['s1'] = $params['s2'] = $params['s3'] = $this->like((string) $filters['search']);
        }
        $whereSql = implode(' AND ', $where);
        $from = 'FROM sponsorships sp LEFT JOIN sponsors s ON s.id = sp.sponsor_id LEFT JOIN athletes a ON a.id = sp.athlete_id';

        $agg = $this->db->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(sp.amount), 0) AS total {$from} WHERE {$whereSql}", $params) ?? [];
        $rows = $this->db->fetchAll(
            self::SELECT . " WHERE {$whereSql} ORDER BY sp.received_at DESC, sp.id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return [
            'items' => array_map([$this, 'map'], $rows),
            'total' => (int) ($agg['n'] ?? 0),
            'sum' => number_format((float) ($agg['total'] ?? 0), 2, '.', ''),
        ];
    }

    public function monthlyTotals(string $from, string $to): array
    {
        return array_map(static fn ($r) => [
            'month' => (string) $r['ym'],
            'total' => number_format((float) $r['total'], 2, '.', ''),
        ], $this->db->fetchAll(
            "SELECT DATE_FORMAT(received_at, '%Y-%m') AS ym, SUM(amount) AS total FROM sponsorships
              WHERE received_at BETWEEN ? AND ? GROUP BY ym ORDER BY ym",
            [$from, $to],
        ));
    }

    public function totalsBySponsor(string $from, string $to): array
    {
        return array_map(static fn ($r) => [
            'sponsor' => (string) $r['sponsor'],
            'total' => number_format((float) $r['total'], 2, '.', ''),
        ], $this->db->fetchAll(
            "SELECT COALESCE(s.name, 'Sem patrocinador vinculado') AS sponsor, SUM(sp.amount) AS total
               FROM sponsorships sp LEFT JOIN sponsors s ON s.id = sp.sponsor_id
              WHERE sp.received_at BETWEEN ? AND ? GROUP BY sponsor ORDER BY total DESC",
            [$from, $to],
        ));
    }

    public function totalBetween(string $from, string $to): string
    {
        return number_format((float) $this->db->fetchValue(
            'SELECT COALESCE(SUM(amount), 0) FROM sponsorships WHERE received_at BETWEEN ? AND ?',
            [$from, $to],
        ), 2, '.', '');
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'sponsor_id' => $r['sponsor_id'] !== null ? (int) $r['sponsor_id'] : null,
            'sponsor_name' => $r['sponsor_name'],
            'athlete_id' => $r['athlete_id'] !== null ? (int) $r['athlete_id'] : null,
            'athlete_name' => $r['athlete_name'],
            'amount' => (string) $r['amount'],
            'received_at' => $r['received_at'],
            'method' => $r['method'],
            'description' => $r['description'],
            'created_by' => $r['created_by_name'],
            'created_at' => $r['created_at'],
        ];
    }
}
