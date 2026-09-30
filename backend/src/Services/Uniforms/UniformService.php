<?php

declare(strict_types=1);

namespace App\Services\Uniforms;

use App\Core\Database\Connection;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\UniformRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\Money;

/**
 * Itens e pedidos de uniforme: pedido → pagamento (total/parcial) → entrega.
 */
class UniformService
{
    public function __construct(
        private UniformRepositoryInterface $uniforms,
        private AthleteRepositoryInterface $athletes,
        private PaymentRepositoryInterface $payments,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    // ---------- Itens ----------

    /** @return list<array<string, mixed>> */
    public function items(bool $onlyActive = false): array
    {
        return $this->uniforms->items($onlyActive);
    }

    /** @param array<string, mixed> $data */
    public function saveItem(?int $id, array $data, Request $request): int
    {
        if ($id !== null && $this->uniforms->findItem($id) === null) {
            throw new NotFoundException('Item não encontrado.');
        }
        if (isset($data['name']) && $this->uniforms->itemNameExists($data['name'], $id)) {
            throw ValidationException::withField('name', 'Já existe um item com este nome.');
        }
        if (array_key_exists('sizes', $data)) {
            $data['sizes'] = $data['sizes'] ? implode(',', array_unique(array_map(
                static fn ($s) => mb_substr(trim((string) $s), 0, 10),
                (array) $data['sizes'],
            ))) : null;
        }
        $savedId = $this->uniforms->saveItem($id, $data);
        $this->audit->log($request, $id === null ? 'uniform_item_created' : 'uniform_item_updated', 'uniform_item', $savedId, array_intersect_key($data, array_flip(['price', 'active'])));

        return $savedId;
    }

    // ---------- Pedidos ----------

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function orders(array $filters, int $page, int $perPage): array
    {
        return $this->uniforms->paginateOrders($filters, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function order(int $id): array
    {
        $order = $this->uniforms->findOrder($id) ?? throw new NotFoundException('Pedido não encontrado.');
        $order['payments'] = $this->payments->forUniformOrder($id);

        return $order;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->uniforms->summary();
    }

    /**
     * @param array{athlete_id: int, item_id: int, size?: ?string, quantity: int, unit_price?: ?string, ordered_at?: ?string,
     *              notes?: ?string, payment?: ?array<string, mixed>} $data
     */
    public function createOrder(array $data, User $user, Request $request): int
    {
        if (!$this->athletes->exists((int) $data['athlete_id'])) {
            throw ValidationException::withField('athlete_id', 'Atleta não encontrado.');
        }
        $item = $this->uniforms->findItem((int) $data['item_id']);
        if ($item === null || !$item['active']) {
            throw ValidationException::withField('item_id', 'Item inválido ou inativo.');
        }
        if (!empty($item['sizes']) && !empty($data['size']) && !in_array($data['size'], $item['sizes'], true)) {
            throw ValidationException::withField('size', 'Tamanho não disponível para este item.');
        }

        $unit = Money::of($data['unit_price'] ?? $item['price']);
        $total = Money::ofCents($unit->cents * (int) $data['quantity']);

        return $this->db->transaction(function () use ($data, $unit, $total, $user, $request): int {
            $id = $this->uniforms->createOrder([
                'athlete_id' => (int) $data['athlete_id'],
                'item_id' => (int) $data['item_id'],
                'size' => $data['size'] ?? null,
                'quantity' => (int) $data['quantity'],
                'unit_price' => $unit->toDecimal(),
                'total' => $total->toDecimal(),
                'paid_amount' => '0.00',
                'status' => $total->isZero() ? UniformStatus::PAID : UniformStatus::PENDING,
                'ordered_at' => $data['ordered_at'] ?? date('Y-m-d'),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->log($request, 'uniform_order_created', 'uniform_order', $id, ['total' => $total->toDecimal()]);

            if (!empty($data['payment']['amount'])) {
                $this->pay($id, $data['payment'], $user, $request);
            }

            return $id;
        });
    }

    /** @param array<string, mixed> $data amount, paid_at, method, notes */
    public function pay(int $orderId, array $data, User $user, Request $request): int
    {
        if (($data['paid_at'] ?? date('Y-m-d')) > date('Y-m-d')) {
            throw ValidationException::withField('paid_at', 'A data do pagamento não pode ser futura.');
        }
        $amount = Money::of((string) $data['amount']);
        if ($amount->cents <= 0) {
            throw ValidationException::withField('amount', 'Informe um valor maior que zero.');
        }

        return $this->db->transaction(function () use ($orderId, $data, $amount, $user, $request): int {
            $order = $this->uniforms->lockOrder($orderId) ?? throw new NotFoundException('Pedido não encontrado.');
            if (!in_array($order['status'], [UniformStatus::PENDING, UniformStatus::PARTIAL], true)) {
                throw new ConflictException('Este pedido não aceita pagamento (situação: ' . $order['status'] . ').');
            }
            $total = Money::of((string) $order['total']);
            $remaining = $total->subtract(Money::of((string) $order['paid_amount']));
            if ($amount->greaterThan($remaining)) {
                throw ValidationException::withField('amount', 'Valor maior que o saldo do pedido (' . $remaining->format() . ').');
            }

            $paymentId = $this->payments->create([
                'uniform_order_id' => $orderId,
                'amount' => $amount->toDecimal(),
                'paid_at' => $data['paid_at'] ?? date('Y-m-d'),
                'method' => $data['method'] ?? 'pix',
                'received_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->recalculate($orderId, $total);
            $this->audit->log($request, 'uniform_payment_registered', 'uniform_order', $orderId, [
                'payment_id' => $paymentId,
                'amount' => $amount->toDecimal(),
            ]);

            return $paymentId;
        });
    }

    /**
     * Chamado pelo PaymentService quando o pagamento é de uniforme.
     *
     * @param array<string, mixed> $payment
     */
    public function reversePayment(array $payment, string $reason, User $user, Request $request): void
    {
        $orderId = (int) $payment['uniform_order_id'];
        $order = $this->uniforms->lockOrder($orderId) ?? throw new NotFoundException('Pedido não encontrado.');
        if ($order['status'] === UniformStatus::CANCELLED) {
            throw new ConflictException('Pedido cancelado.');
        }
        $this->payments->reverse((int) $payment['id'], $user->id, $reason);
        $this->recalculate($orderId, Money::of((string) $order['total']));
        $this->audit->log($request, 'uniform_payment_reversed', 'uniform_order', $orderId, [
            'payment_id' => (int) $payment['id'],
            'amount' => $payment['amount'],
            'reason' => $reason,
        ]);
    }

    public function setDelivered(int $orderId, bool $delivered, User $user, Request $request): void
    {
        $order = $this->uniforms->lockOrder($orderId) ?? throw new NotFoundException('Pedido não encontrado.');
        if ($order['status'] === UniformStatus::CANCELLED) {
            throw new ConflictException('Pedido cancelado.');
        }
        $this->uniforms->updateOrder($orderId, [
            'delivered' => $delivered,
            'delivered_at' => $delivered ? date('Y-m-d H:i:s') : null,
            'delivered_by' => $delivered ? $user->id : null,
        ]);
        $this->audit->log($request, $delivered ? 'uniform_delivered' : 'uniform_delivery_undone', 'uniform_order', $orderId);
    }

    public function cancel(int $orderId, string $reason, Request $request): void
    {
        $this->db->transaction(function () use ($orderId, $reason, $request): void {
            $order = $this->uniforms->lockOrder($orderId) ?? throw new NotFoundException('Pedido não encontrado.');
            if ($order['status'] === UniformStatus::CANCELLED) {
                throw new ConflictException('Pedido já cancelado.');
            }
            if (Money::of($this->payments->activeTotalForUniformOrder($orderId))->cents > 0) {
                throw new ConflictException('Há pagamentos neste pedido. Estorne-os antes de cancelar.');
            }
            if ((bool) $order['delivered']) {
                throw new ConflictException('Pedido já entregue não pode ser cancelado.');
            }
            $this->uniforms->updateOrder($orderId, [
                'status' => UniformStatus::CANCELLED,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancel_reason' => $reason,
            ]);
            $this->audit->log($request, 'uniform_order_cancelled', 'uniform_order', $orderId, ['reason' => $reason]);
        });
    }

    private function recalculate(int $orderId, Money $total): void
    {
        $paid = Money::of($this->payments->activeTotalForUniformOrder($orderId));
        $this->uniforms->updateOrder($orderId, [
            'paid_amount' => $paid->toDecimal(),
            'status' => UniformStatus::resolve($total, $paid),
        ]);
    }
}
