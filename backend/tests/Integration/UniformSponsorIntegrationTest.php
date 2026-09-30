<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ValidationException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Pdo\PdoAthleteRepository;
use App\Repositories\Pdo\PdoAuditLogRepository;
use App\Repositories\Pdo\PdoInvoiceRepository;
use App\Repositories\Pdo\PdoPaymentRepository;
use App\Repositories\Pdo\PdoSponsorRepository;
use App\Repositories\Pdo\PdoSponsorshipRepository;
use App\Repositories\Pdo\PdoUniformRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\PaymentService;
use App\Services\Sponsors\SponsorService;
use App\Services\Uniforms\UniformService;
use App\Support\PhoneNormalizer;
use RuntimeException;
use Tests\Fakes\RequestFactory;

/**
 * Pedido de uniforme → pagamento parcial → estorno pela rota geral →
 * entrega → cancelamento; patrocinador + entrada. Tudo desfeito ao final.
 */
final class UniformSponsorIntegrationTest extends DatabaseTestCase
{
    public function testUniformAndSponsorFlows(): void
    {
        $db = self::$db;
        $userId = (int) $db->fetchValue('SELECT id FROM users ORDER BY id LIMIT 1');
        $athleteId = (int) $db->fetchValue('SELECT id FROM athletes WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
        if ($userId === 0 || $athleteId === 0) {
            self::markTestSkipped('Banco sem usuário/atleta.');
        }
        $user = new User($userId, 'Admin', 'a@x', Role::Admin, true, false);
        $req = RequestFactory::make('POST', '/api/x');
        $audit = new AuditLogger(new PdoAuditLogRepository($db));
        $payments = new PdoPaymentRepository($db);
        $uniforms = new UniformService(new PdoUniformRepository($db), new PdoAthleteRepository($db), $payments, $db, $audit);
        $paymentService = new PaymentService(new PdoInvoiceRepository($db), $payments, $db, $audit, $uniforms);
        $sponsors = new SponsorService(new PdoSponsorRepository($db), new PdoSponsorshipRepository($db), new PdoAthleteRepository($db), new PhoneNormalizer(), $audit);

        try {
            $db->transaction(function () use ($uniforms, $paymentService, $sponsors, $user, $req, $athleteId): void {
                $itemId = $uniforms->saveItem(null, ['name' => 'Teste Integração', 'price' => '50.00', 'sizes' => ['P', 'M']], $req);

                try {
                    $uniforms->createOrder(['athlete_id' => $athleteId, 'item_id' => $itemId, 'size' => 'XG', 'quantity' => 1], $user, $req);
                    self::fail('Tamanho inexistente deveria falhar');
                } catch (ValidationException $e) {
                    self::assertArrayHasKey('size', $e->errors());
                }

                $orderId = $uniforms->createOrder([
                    'athlete_id' => $athleteId, 'item_id' => $itemId, 'size' => 'M', 'quantity' => 2,
                    'payment' => ['amount' => '30.00', 'paid_at' => date('Y-m-d'), 'method' => 'pix'],
                ], $user, $req);
                $order = $uniforms->order($orderId);
                self::assertSame('100.00', $order['total']);
                self::assertSame('pago_parcial', $order['status']);

                // Estorno pela rota geral de pagamentos (delegado ao módulo de uniformes).
                $paymentService->reverse($order['payments'][0]['id'], 'teste', $user, $req);
                self::assertSame('pendente', $uniforms->order($orderId)['status']);

                $uniforms->setDelivered($orderId, true, $user, $req);
                try {
                    $uniforms->cancel($orderId, 'teste', $req);
                    self::fail('Entregue não pode ser cancelado');
                } catch (ConflictException) {
                }
                $uniforms->setDelivered($orderId, false, $user, $req);
                $uniforms->cancel($orderId, 'desistiu', $req);
                self::assertSame('cancelado', $uniforms->order($orderId)['status']);

                // Patrocínios
                $sponsorId = $sponsors->saveSponsor(null, ['name' => 'Padaria Teste', 'phone' => '(18) 99990-0101', 'document' => '11.222.333/0001-81'], $req);
                try {
                    $sponsors->addEntry(['amount' => '10.00', 'received_at' => date('Y-m-d'), 'method' => 'pix'], $user, $req);
                    self::fail('Entrada sem patrocinador e sem descrição deveria falhar');
                } catch (ValidationException) {
                }
                $sponsors->addEntry(['sponsor_id' => $sponsorId, 'athlete_id' => $athleteId, 'amount' => '500.00', 'received_at' => date('Y-m-d'), 'method' => 'pix'], $user, $req);
                try {
                    $sponsors->deleteSponsor($sponsorId, $req);
                    self::fail('Patrocinador com entradas não pode ser excluído');
                } catch (ConflictException) {
                }
                $summary = $sponsors->yearSummary((int) date('Y'));
                self::assertCount(12, $summary['by_month']);
                self::assertContains('Padaria Teste', array_column($summary['by_sponsor'], 'sponsor'));

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }

        self::assertNull($db->fetchValue("SELECT id FROM uniform_items WHERE name = 'Teste Integração'"));
    }
}
