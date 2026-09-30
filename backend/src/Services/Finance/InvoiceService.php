<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database\Connection;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\Money;

/**
 * Consulta e ajustes manuais de mensalidades (vencimento, desconto,
 * observação, cancelamento).
 */
class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private PaymentService $paymentService,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        return $this->invoices->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFoundException('Mensalidade não encontrada.');
        $invoice['payments'] = $this->payments->forInvoice($id);

        return $invoice;
    }

    /** @return list<array<string, mixed>> */
    public function forAthlete(int $athleteId): array
    {
        return $this->invoices->forAthlete($athleteId);
    }

    /**
     * @param array{due_date?: string, discount?: string, notes?: ?string} $data
     */
    public function update(int $id, array $data, Request $request): void
    {
        $this->db->transaction(function () use ($id, $data, $request): void {
            $invoice = $this->invoices->lockForUpdate($id) ?? throw new NotFoundException('Mensalidade não encontrada.');
            if ($invoice['status'] === InvoiceStatus::CANCELLED) {
                throw new ConflictException('Mensalidade cancelada não pode ser alterada.');
            }

            $changes = array_intersect_key($data, array_flip(['due_date', 'notes']));

            if (isset($data['discount'])) {
                $amount = Money::of((string) $invoice['amount']);
                $discount = Money::of($data['discount']);
                if ($discount->greaterThan($amount)) {
                    throw ValidationException::withField('discount', 'Desconto maior que o valor da mensalidade.');
                }
                $final = $amount->subtract($discount);
                if (Money::of((string) $invoice['paid_amount'])->greaterThan($final)) {
                    throw ValidationException::withField('discount', 'O valor já pago é maior que o novo total. Estorne antes.');
                }
                $changes['discount'] = $discount->toDecimal();
                $changes['final_amount'] = $final->toDecimal();
            }

            $this->invoices->update($id, $changes);
            $this->paymentService->recalculate($id, Money::of((string) ($changes['final_amount'] ?? $invoice['final_amount'])));
            $this->audit->log($request, 'invoice_updated', 'invoice', $id, $changes);
        });
    }

    public function cancel(int $id, string $reason, User $user, Request $request): void
    {
        $this->db->transaction(function () use ($id, $reason, $user, $request): void {
            $invoice = $this->invoices->lockForUpdate($id) ?? throw new NotFoundException('Mensalidade não encontrada.');
            if ($invoice['status'] === InvoiceStatus::CANCELLED) {
                throw new ConflictException('Mensalidade já cancelada.');
            }
            if (Money::of($this->payments->activeTotalForInvoice($id))->cents > 0) {
                throw new ConflictException('Há pagamentos nesta mensalidade. Estorne-os antes de cancelar.');
            }

            $this->invoices->update($id, [
                'status' => InvoiceStatus::CANCELLED,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);
            $this->audit->log($request, 'invoice_cancelled', 'invoice', $id, ['reason' => $reason]);
        });
    }
}
