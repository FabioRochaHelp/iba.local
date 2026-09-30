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
use App\Services\Uniforms\UniformService;
use App\Support\Money;

/**
 * Baixa (total/parcial) e estorno de pagamentos de mensalidade.
 * Pagamento nunca é apagado: estorno marca o registro e recalcula o saldo.
 */
class PaymentService
{
    public const METHODS = ['pix', 'dinheiro', 'cartao', 'transferencia'];

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private Connection $db,
        private AuditLogger $audit,
        private UniformService $uniforms,
    ) {
    }

    /**
     * @param array{amount: string, paid_at: string, method: string, notes?: ?string} $data
     */
    public function register(int $invoiceId, array $data, User $user, Request $request): int
    {
        if ($data['paid_at'] > date('Y-m-d')) {
            throw ValidationException::withField('paid_at', 'A data do pagamento não pode ser futura.');
        }
        $amount = Money::of($data['amount']);
        if ($amount->cents <= 0) {
            throw ValidationException::withField('amount', 'Informe um valor maior que zero.');
        }

        return $this->db->transaction(function () use ($invoiceId, $data, $amount, $user, $request): int {
            // FOR UPDATE: duas baixas simultâneas não ultrapassam o saldo.
            $invoice = $this->invoices->lockForUpdate($invoiceId) ?? throw new NotFoundException('Mensalidade não encontrada.');
            if (!InvoiceStatus::acceptsPayment((string) $invoice['status'])) {
                throw new ConflictException('Esta mensalidade não aceita pagamento (situação: ' . $invoice['status'] . ').');
            }

            $final = Money::of((string) $invoice['final_amount']);
            $remaining = $final->subtract(Money::of((string) $invoice['paid_amount']));
            if ($amount->greaterThan($remaining)) {
                throw ValidationException::withField('amount', 'Valor maior que o saldo em aberto (' . $remaining->format() . ').');
            }

            $paymentId = $this->payments->create([
                'invoice_id' => $invoiceId,
                'amount' => $amount->toDecimal(),
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'received_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->recalculate($invoiceId, $final);

            $this->audit->log($request, 'payment_registered', 'invoice', $invoiceId, [
                'payment_id' => $paymentId,
                'amount' => $amount->toDecimal(),
                'method' => $data['method'],
            ]);

            return $paymentId;
        });
    }

    public function reverse(int $paymentId, string $reason, User $user, Request $request): void
    {
        $this->db->transaction(function () use ($paymentId, $reason, $user, $request): void {
            $payment = $this->payments->find($paymentId) ?? throw new NotFoundException('Pagamento não encontrado.');
            if ($payment['reversed_at'] !== null) {
                throw new ConflictException('Este pagamento já foi estornado.');
            }
            if ($payment['invoice_id'] === null) {
                // Pagamento de uniforme: regra própria do módulo de uniformes.
                $this->uniforms->reversePayment($payment, $reason, $user, $request);

                return;
            }

            $invoiceId = (int) $payment['invoice_id'];
            $invoice = $this->invoices->lockForUpdate($invoiceId) ?? throw new NotFoundException('Mensalidade não encontrada.');
            if ($invoice['status'] === InvoiceStatus::CANCELLED) {
                throw new ConflictException('Mensalidade cancelada.');
            }

            $this->payments->reverse($paymentId, $user->id, $reason);
            $this->recalculate($invoiceId, Money::of((string) $invoice['final_amount']));

            $this->audit->log($request, 'payment_reversed', 'invoice', $invoiceId, [
                'payment_id' => $paymentId,
                'amount' => $payment['amount'],
                'reason' => $reason,
            ]);
        });
    }

    /** Saldo e status derivados SEMPRE da soma dos pagamentos válidos. */
    public function recalculate(int $invoiceId, Money $finalAmount): void
    {
        $paid = Money::of($this->payments->activeTotalForInvoice($invoiceId));
        $this->invoices->update($invoiceId, [
            'paid_amount' => $paid->toDecimal(),
            'status' => InvoiceStatus::resolve($finalAmount, $paid),
        ]);
    }
}
