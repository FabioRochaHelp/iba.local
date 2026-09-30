<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config\Config;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ValidationException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Pdo\PdoAthletePlanRepository;
use App\Repositories\Pdo\PdoAthleteRepository;
use App\Repositories\Pdo\PdoUniformRepository;
use App\Services\Uniforms\UniformService;
use App\Repositories\Pdo\PdoAuditLogRepository;
use App\Repositories\Pdo\PdoInvoiceRepository;
use App\Repositories\Pdo\PdoPaymentRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Discounts\DiscountCalculator;
use App\Services\Finance\InvoiceGeneratorService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use RuntimeException;
use Tests\Fakes\RequestFactory;

/**
 * Fluxo completo gerar → pagar → estornar → cancelar em um mês futuro,
 * dentro de uma transação desfeita ao final (não suja o banco).
 */
final class FinanceFlowIntegrationTest extends DatabaseTestCase
{
    private const MONTH = '2099-03';

    public function testFullFlow(): void
    {
        $db = self::$db;
        $userId = (int) $db->fetchValue('SELECT id FROM users ORDER BY id LIMIT 1');
        if ($userId === 0) {
            self::markTestSkipped('Sem usuário no banco.');
        }
        $user = new User($userId, 'Admin', 'a@x', Role::Admin, true, false);
        $req = RequestFactory::make('POST', '/api/x');

        $invoices = new PdoInvoiceRepository($db);
        $payments = new PdoPaymentRepository($db);
        $audit = new AuditLogger(new PdoAuditLogRepository($db));
        $generator = new InvoiceGeneratorService(
            new PdoAthletePlanRepository($db), $invoices, DiscountCalculator::default(), $db, $audit,
            new Config(['finance' => ['due_day' => 10]]),
        );
        $uniformService = new UniformService(new PdoUniformRepository($db), new PdoAthleteRepository($db), $payments, $db, $audit);
        $paymentService = new PaymentService($invoices, $payments, $db, $audit, $uniformService);
        $invoiceService = new InvoiceService($invoices, $payments, $paymentService, $db, $audit);

        try {
            $db->transaction(function () use ($generator, $invoices, $paymentService, $invoiceService, $user, $req): void {
                $first = $generator->generate(self::MONTH);
                $again = $generator->generate(self::MONTH);

                self::assertGreaterThan(0, $first['created']);
                self::assertSame(0, $again['created'], 'geração é idempotente');
                self::assertSame($first['created'], $again['skipped']);

                $page = $invoices->paginate(['month' => self::MONTH, 'status' => 'aberta'], 1, 1);
                $inv = $page['items'][0];
                self::assertSame('2099-03-10', $inv['due_date']);

                // Pagamento maior que o saldo é recusado.
                try {
                    $paymentService->register($inv['id'], ['amount' => '9999.00', 'paid_at' => date('Y-m-d'), 'method' => 'pix'], $user, $req);
                    self::fail('Esperava ValidationException');
                } catch (ValidationException $e) {
                    self::assertArrayHasKey('amount', $e->errors());
                }

                // Parcial → paga.
                $half = number_format((float) $inv['final_amount'] / 2, 2, '.', '');
                $p1 = $paymentService->register($inv['id'], ['amount' => $half, 'paid_at' => date('Y-m-d'), 'method' => 'pix'], $user, $req);
                self::assertSame('parcial', $invoices->find($inv['id'])['status']);
                $rest = number_format((float) $inv['final_amount'] - (float) $half, 2, '.', '');
                $paymentService->register($inv['id'], ['amount' => $rest, 'paid_at' => date('Y-m-d'), 'method' => 'dinheiro'], $user, $req);
                self::assertSame('paga', $invoices->find($inv['id'])['status']);

                // Paga não aceita mais pagamento.
                try {
                    $paymentService->register($inv['id'], ['amount' => '1.00', 'paid_at' => date('Y-m-d'), 'method' => 'pix'], $user, $req);
                    self::fail('Esperava ConflictException');
                } catch (ConflictException) {
                }

                // Estorno recalcula; não dá para cancelar com pagamento ativo.
                $paymentService->reverse($p1, 'Lançado em duplicidade', $user, $req);
                $after = $invoices->find($inv['id']);
                self::assertSame('parcial', $after['status']);
                self::assertSame($rest, $after['paid_amount']);
                try {
                    $invoiceService->cancel($inv['id'], 'teste', $user, $req);
                    self::fail('Esperava ConflictException');
                } catch (ConflictException) {
                }

                // Desconto ajustado recalcula total e status.
                $invoiceService->update($inv['id'], ['discount' => number_format((float) $inv['amount'] - (float) $rest, 2, '.', '')], $req);
                self::assertSame('paga', $invoices->find($inv['id'])['status']);

                // Summary do mês bate.
                $summary = $invoices->monthSummary(self::MONTH . '-01');
                self::assertSame($first['created'], $summary['total_count']);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }

        self::assertSame(0, (int) $db->fetchValue("SELECT COUNT(*) FROM invoices WHERE reference_month = '2099-03-01'"));
    }
}
