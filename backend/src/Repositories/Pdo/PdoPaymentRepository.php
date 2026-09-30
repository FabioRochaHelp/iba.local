<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const COLUMNS = ['invoice_id', 'uniform_order_id', 'amount', 'paid_at', 'method', 'received_by', 'notes'];

    public function __construct(private Connection $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert('payments', array_intersect_key($data, array_flip(self::COLUMNS)));
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM payments WHERE id = ?', [$id]);
    }

    public function forInvoice(int $invoiceId): array
    {
        return $this->listBy('invoice_id', $invoiceId);
    }

    public function forUniformOrder(int $orderId): array
    {
        return $this->listBy('uniform_order_id', $orderId);
    }

    public function activeTotalForUniformOrder(int $orderId): string
    {
        return number_format((float) $this->db->fetchValue(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE uniform_order_id = ? AND reversed_at IS NULL',
            [$orderId],
        ), 2, '.', '');
    }

    /**
     * @param 'invoice_id'|'uniform_order_id' $column coluna fixa (nunca vinda do usuário)
     * @return list<array<string, mixed>>
     */
    private function listBy(string $column, int $id): array
    {
        if (!in_array($column, ['invoice_id', 'uniform_order_id'], true)) {
            throw new \InvalidArgumentException('Coluna inválida.');
        }

        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'amount' => (string) $r['amount'],
            'paid_at' => $r['paid_at'],
            'method' => $r['method'],
            'notes' => $r['notes'],
            'received_by' => $r['received_by_name'],
            'created_at' => $r['created_at'],
            'reversed_at' => $r['reversed_at'],
            'reversed_by' => $r['reversed_by_name'],
            'reversal_reason' => $r['reversal_reason'],
        ], $this->db->fetchAll(
            'SELECT p.*, u.name AS received_by_name, r.name AS reversed_by_name
               FROM payments p
          LEFT JOIN users u ON u.id = p.received_by
          LEFT JOIN users r ON r.id = p.reversed_by
              WHERE p.' . $column . ' = ? ORDER BY p.paid_at, p.id',
            [$id],
        ));
    }

    public function activeTotalForInvoice(int $invoiceId): string
    {
        return number_format((float) $this->db->fetchValue(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND reversed_at IS NULL',
            [$invoiceId],
        ), 2, '.', '');
    }

    public function reverse(int $id, int $userId, string $reason): void
    {
        $this->db->execute(
            'UPDATE payments SET reversed_at = NOW(), reversed_by = ?, reversal_reason = ? WHERE id = ? AND reversed_at IS NULL',
            [$userId, $reason, $id],
        );
    }

    public function receivedBetween(string $from, string $to): string
    {
        return number_format((float) $this->db->fetchValue(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reversed_at IS NULL AND paid_at BETWEEN ? AND ?',
            [$from, $to],
        ), 2, '.', '');
    }
}
