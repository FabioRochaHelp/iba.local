<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\UniformRepositoryInterface;
use App\Repositories\Pdo\Concerns\BuildsListing;

final class PdoUniformRepository implements UniformRepositoryInterface
{
    use BuildsListing;

    private const ITEM_COLUMNS = ['name', 'price', 'sizes', 'active'];

    private const ORDER_COLUMNS = [
        'athlete_id', 'item_id', 'size', 'quantity', 'unit_price', 'total', 'paid_amount', 'status',
        'delivered', 'delivered_at', 'delivered_by', 'ordered_at', 'notes', 'cancelled_at', 'cancel_reason',
    ];

    private const ORDER_SELECT = "SELECT o.id, o.athlete_id, o.item_id, o.size, o.quantity, o.unit_price, o.total, o.paid_amount,
            (o.total - o.paid_amount) AS remaining, o.status, o.delivered, o.delivered_at, o.ordered_at, o.notes,
            o.cancelled_at, o.cancel_reason,
            a.name AS athlete_name, g.name AS guardian_name, i.name AS item_name, u.name AS delivered_by_name
       FROM uniform_orders o
       JOIN athletes a ON a.id = o.athlete_id
       JOIN guardians g ON g.id = a.guardian_id
       JOIN uniform_items i ON i.id = o.item_id
  LEFT JOIN users u ON u.id = o.delivered_by";

    public function __construct(private Connection $db)
    {
    }

    public function findItemIdByName(string $name): ?int
    {
        $id = $this->db->fetchValue('SELECT id FROM uniform_items WHERE name = ?', [$name]);

        return $id === null ? null : (int) $id;
    }

    public function createItem(string $name, string $price): int
    {
        return $this->db->insert('uniform_items', ['name' => $name, 'price' => $price]);
    }

    public function items(bool $onlyActive = false): array
    {
        return array_map([$this, 'mapItem'], $this->db->fetchAll(
            'SELECT i.id, i.name, i.price, i.sizes, i.active,
                    (SELECT COUNT(*) FROM uniform_orders o WHERE o.item_id = i.id AND o.status <> \'cancelado\') AS orders_count
               FROM uniform_items i ' . ($onlyActive ? 'WHERE i.active = 1 ' : '') . 'ORDER BY i.active DESC, i.name',
        ));
    }

    public function findItem(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT id, name, price, sizes, active, 0 AS orders_count FROM uniform_items WHERE id = ?', [$id]);

        return $row ? $this->mapItem($row) : null;
    }

    public function saveItem(?int $id, array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::ITEM_COLUMNS));
        if (array_key_exists('active', $data)) {
            $data['active'] = (int) $data['active'];
        }
        if ($id === null) {
            return $this->db->insert('uniform_items', $data);
        }
        $this->db->update('uniform_items', $data, ['id' => $id]);

        return $id;
    }

    public function itemNameExists(string $name, ?int $exceptId = null): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM uniform_items WHERE name = ? AND id <> ?', [$name, $exceptId ?? 0]) !== null;
    }

    public function createOrder(array $data): int
    {
        return $this->db->insert('uniform_orders', array_intersect_key($data, array_flip(self::ORDER_COLUMNS)));
    }

    public function findOrder(int $id): ?array
    {
        $row = $this->db->fetchOne(self::ORDER_SELECT . ' WHERE o.id = ?', [$id]);

        return $row ? $this->mapOrder($row) : null;
    }

    public function lockOrder(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM uniform_orders WHERE id = ? FOR UPDATE', [$id]);
    }

    public function updateOrder(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::ORDER_COLUMNS));
        if (array_key_exists('delivered', $data)) {
            $data['delivered'] = (int) $data['delivered'];
        }
        $this->db->update('uniform_orders', $data, ['id' => $id]);
    }

    public function paginateOrders(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = '(a.name LIKE :s1 OR g.name LIKE :s2)';
            $params['s1'] = $this->like((string) $filters['search']);
            $params['s2'] = $this->like((string) $filters['search']);
        }
        $status = $filters['status'] ?? null;
        if ($status === 'a_receber') {
            $where[] = "o.status IN ('pendente','pago_parcial')";
        } elseif (!empty($status)) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        } else {
            $where[] = "o.status <> 'cancelado'";
        }
        if (($filters['delivery'] ?? null) === 'pendente') {
            $where[] = "o.delivered = 0 AND o.status <> 'cancelado'";
        } elseif (($filters['delivery'] ?? null) === 'entregue') {
            $where[] = 'o.delivered = 1';
        }
        if (!empty($filters['athlete_id'])) {
            $where[] = 'o.athlete_id = :athlete_id';
            $params['athlete_id'] = (int) $filters['athlete_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'o.item_id = :item_id';
            $params['item_id'] = (int) $filters['item_id'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM uniform_orders o JOIN athletes a ON a.id = o.athlete_id JOIN guardians g ON g.id = a.guardian_id WHERE {$whereSql}",
            $params,
        );
        $rows = $this->db->fetchAll(
            self::ORDER_SELECT . " WHERE {$whereSql} ORDER BY o.ordered_at DESC, o.id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return ['items' => array_map([$this, 'mapOrder'], $rows), 'total' => $total];
    }

    public function summary(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(total), 0) AS total, COALESCE(SUM(paid_amount), 0) AS received,
                    COALESCE(SUM(CASE WHEN status IN ('pendente','pago_parcial') THEN total - paid_amount END), 0) AS receivable,
                    SUM(status IN ('pendente','pago_parcial')) AS receivable_count,
                    SUM(delivered = 0) AS to_deliver_count,
                    COUNT(*) AS orders_count
               FROM uniform_orders WHERE status <> 'cancelado'",
        ) ?? [];

        return [
            'total' => number_format((float) ($row['total'] ?? 0), 2, '.', ''),
            'received' => number_format((float) ($row['received'] ?? 0), 2, '.', ''),
            'receivable' => number_format((float) ($row['receivable'] ?? 0), 2, '.', ''),
            'receivable_count' => (int) ($row['receivable_count'] ?? 0),
            'to_deliver_count' => (int) ($row['to_deliver_count'] ?? 0),
            'orders_count' => (int) ($row['orders_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function mapItem(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'price' => (string) $r['price'],
            'sizes' => $r['sizes'] ? array_values(array_filter(array_map('trim', explode(',', (string) $r['sizes'])))) : [],
            'active' => (bool) $r['active'],
            'orders_count' => (int) $r['orders_count'],
        ];
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function mapOrder(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'athlete_id' => (int) $r['athlete_id'],
            'athlete_name' => $r['athlete_name'],
            'guardian_name' => $r['guardian_name'],
            'item_id' => (int) $r['item_id'],
            'item_name' => $r['item_name'],
            'size' => $r['size'],
            'quantity' => (int) $r['quantity'],
            'unit_price' => (string) $r['unit_price'],
            'total' => (string) $r['total'],
            'paid_amount' => (string) $r['paid_amount'],
            'remaining' => number_format(max(0, (float) $r['remaining']), 2, '.', ''),
            'status' => $r['status'],
            'delivered' => (bool) $r['delivered'],
            'delivered_at' => $r['delivered_at'],
            'delivered_by' => $r['delivered_by_name'],
            'ordered_at' => $r['ordered_at'],
            'notes' => $r['notes'],
            'cancelled_at' => $r['cancelled_at'],
            'cancel_reason' => $r['cancel_reason'],
        ];
    }
}
