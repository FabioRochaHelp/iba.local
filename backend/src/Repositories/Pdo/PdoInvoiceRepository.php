<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoInvoiceRepository implements InvoiceRepositoryInterface
{
    use BuildsListing;

    private const COLUMNS = [
        'athlete_id', 'athlete_plan_id', 'reference_month', 'amount', 'discount', 'final_amount',
        'paid_amount', 'due_date', 'status', 'notes', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    private const SORTS = [
        'athlete' => 'a.name',
        'due_date' => 'i.due_date',
        'final_amount' => 'i.final_amount',
        'status' => 'i.status',
        'reference_month' => 'i.reference_month',
    ];

    /** Aberta/parcial com vencimento passado = atrasada. */
    private const OVERDUE_SQL = "(i.status IN ('aberta','parcial') AND i.due_date < CURDATE())";

    private const SELECT = "SELECT i.id, i.athlete_id, i.athlete_plan_id, i.reference_month, i.amount, i.discount, i.final_amount,
                i.paid_amount, (i.final_amount - i.paid_amount) AS remaining, i.due_date, i.status, i.notes,
                i.cancelled_at, i.cancel_reason, i.created_at,
                (i.status IN ('aberta','parcial') AND i.due_date < CURDATE()) AS overdue,
                a.name AS athlete_name, a.status AS athlete_status,
                g.id AS guardian_id, g.name AS guardian_name,
                (SELECT gp.phone FROM guardian_phones gp WHERE gp.guardian_id = g.id ORDER BY gp.is_whatsapp DESC, gp.id LIMIT 1) AS guardian_phone,
                p.name AS plan_name
           FROM invoices i
           JOIN athletes a ON a.id = i.athlete_id
           JOIN guardians g ON g.id = a.guardian_id
      LEFT JOIN athlete_plans ap ON ap.id = i.athlete_plan_id
      LEFT JOIN plans p ON p.id = ap.plan_id";

    public function __construct(private Connection $db)
    {
    }

    public function findByAthleteAndMonth(int $athleteId, string $referenceMonth): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM invoices WHERE athlete_id = ? AND reference_month = ?',
            [$athleteId, $referenceMonth],
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE i.id = ?', [$id]);

        return $row ? $this->map($row) : null;
    }

    public function lockForUpdate(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$id]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['month'])) {
            $where[] = 'i.reference_month = :month';
            $params['month'] = $filters['month'] . '-01';
        }
        if (!empty($filters['athlete_id'])) {
            $where[] = 'i.athlete_id = :athlete_id';
            $params['athlete_id'] = (int) $filters['athlete_id'];
        }
        $status = $filters['status'] ?? null;
        if ($status === 'atrasada') {
            $where[] = self::OVERDUE_SQL;
        } elseif ($status === 'pendente') {
            $where[] = "i.status IN ('aberta','parcial')";
        } elseif (!empty($status)) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        if (!empty($filters['search'])) {
            $where[] = '(a.name LIKE :s1 OR g.name LIKE :s2)';
            $params['s1'] = $this->like((string) $filters['search']);
            $params['s2'] = $this->like((string) $filters['search']);
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM invoices i JOIN athletes a ON a.id = i.athlete_id JOIN guardians g ON g.id = a.guardian_id WHERE {$whereSql}",
            $params,
        );
        $order = $this->orderBy($filters['sort'] ?? null, $filters['order'] ?? null, self::SORTS, 'athlete');
        $rows = $this->db->fetchAll(
            self::SELECT . " WHERE {$whereSql} ORDER BY {$order}, i.id LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return ['items' => array_map([$this, 'map'], $rows), 'total' => $total];
    }

    public function forAthlete(int $athleteId): array
    {
        return array_map(
            [$this, 'map'],
            $this->db->fetchAll(self::SELECT . ' WHERE i.athlete_id = ? ORDER BY i.reference_month DESC', [$athleteId]),
        );
    }

    public function billedAthleteIds(string $referenceMonth): array
    {
        return array_map('intval', array_column(
            $this->db->fetchAll('SELECT athlete_id FROM invoices WHERE reference_month = ?', [$referenceMonth]),
            'athlete_id',
        ));
    }

    public function monthSummary(string $referenceMonth): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status <> 'cancelada' THEN final_amount END), 0) AS expected,
                COALESCE(SUM(CASE WHEN status <> 'cancelada' THEN paid_amount END), 0) AS received,
                COALESCE(SUM(CASE WHEN status IN ('aberta','parcial') THEN final_amount - paid_amount END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status IN ('aberta','parcial') AND due_date < CURDATE() THEN final_amount - paid_amount END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN status <> 'cancelada' THEN discount END), 0) AS discounts,
                SUM(status = 'paga') AS paid_count,
                SUM(status = 'parcial') AS partial_count,
                SUM(status = 'aberta') AS open_count,
                SUM(status = 'cortesia') AS courtesy_count,
                SUM(status = 'cancelada') AS cancelled_count,
                SUM(status IN ('aberta','parcial') AND due_date < CURDATE()) AS overdue_count
               FROM invoices WHERE reference_month = ?",
            [$referenceMonth],
        ) ?? [];

        $money = ['expected', 'received', 'pending', 'overdue', 'discounts'];
        $result = [];
        foreach ($row as $key => $value) {
            $result[$key] = in_array($key, $money, true) ? number_format((float) $value, 2, '.', '') : (int) $value;
        }

        return $result;
    }

    public function monthlySeries(string $fromMonth, string $toMonth): array
    {
        return array_map(static fn (array $r) => [
            'month' => substr((string) $r['reference_month'], 0, 7),
            'expected' => number_format((float) $r['expected'], 2, '.', ''),
            'received' => number_format((float) $r['received'], 2, '.', ''),
        ], $this->db->fetchAll(
            "SELECT reference_month, SUM(final_amount) AS expected, SUM(paid_amount) AS received
               FROM invoices WHERE status <> 'cancelada' AND reference_month BETWEEN ? AND ?
              GROUP BY reference_month ORDER BY reference_month",
            [$fromMonth, $toMonth],
        ));
    }

    public function overdue(string $today): array
    {
        return array_map([$this, 'map'], $this->db->fetchAll(
            self::SELECT . " WHERE i.status IN ('aberta','parcial') AND i.due_date < ? AND a.deleted_at IS NULL
                             ORDER BY g.name, a.name, i.reference_month",
            [$today],
        ));
    }

    public function create(array $data): int
    {
        return $this->db->insert('invoices', array_intersect_key($data, array_flip(self::COLUMNS)));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('invoices', array_intersect_key($data, array_flip(self::COLUMNS)), ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'athlete_id' => (int) $r['athlete_id'],
            'athlete_name' => $r['athlete_name'],
            'athlete_status' => $r['athlete_status'],
            'guardian_id' => (int) $r['guardian_id'],
            'guardian_name' => $r['guardian_name'],
            'guardian_phone' => $r['guardian_phone'],
            'plan_name' => $r['plan_name'],
            'reference_month' => substr((string) $r['reference_month'], 0, 7),
            'amount' => (string) $r['amount'],
            'discount' => (string) $r['discount'],
            'final_amount' => (string) $r['final_amount'],
            'paid_amount' => (string) $r['paid_amount'],
            'remaining' => number_format(max(0, (float) $r['remaining']), 2, '.', ''),
            'due_date' => $r['due_date'],
            'status' => $r['status'],
            'overdue' => (bool) $r['overdue'],
            'notes' => $r['notes'],
            'cancelled_at' => $r['cancelled_at'],
            'cancel_reason' => $r['cancel_reason'],
        ];
    }
}
