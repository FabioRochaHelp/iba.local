<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AthletePlanRepositoryInterface;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\GuardianRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PositionRepositoryInterface;
use App\Repositories\Contracts\SponsorshipRepositoryInterface;
use App\Repositories\Contracts\UniformRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use DateTimeImmutable;
use RuntimeException;

/**
 * Importa a planilha "Cadastro de Atletas" (Google Forms).
 *
 * Idempotente: atleta já existente (mesmo nome + nascimento) é ignorado,
 * então a importação pode ser repetida sem duplicar dados.
 * Tudo roda em uma transação; --dry-run desfaz ao final.
 */
class AthleteImportService
{
    public const UNIFORM_ITEM = 'Uniforme (importado da planilha)';
    private const NOTE = 'Importado da planilha';

    /** @param list<SpreadsheetReaderInterface> $readers */
    public function __construct(
        private array $readers,
        private SpreadsheetRowParser $parser,
        private Connection $db,
        private AthleteRepositoryInterface $athletes,
        private GuardianRepositoryInterface $guardians,
        private PositionRepositoryInterface $positions,
        private PlanRepositoryInterface $plans,
        private AthletePlanRepositoryInterface $athletePlans,
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private UniformRepositoryInterface $uniforms,
        private SponsorshipRepositoryInterface $sponsorships,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array{month?: ?string, dry_run?: bool, open_unpaid?: bool} $options
     * @return array<string, mixed> relatório
     */
    public function importFile(string $path, array $options = []): array
    {
        $reader = null;
        foreach ($this->readers as $candidate) {
            if ($candidate->supports($path)) {
                $reader = $candidate;
                break;
            }
        }
        if ($reader === null) {
            throw new RuntimeException('Formato não suportado. Use .csv ou .xlsx.');
        }

        $rows = $reader->read($path);
        if ($rows === []) {
            throw new RuntimeException('A planilha não tem linhas de dados.');
        }

        $map = $this->parser->mapHeaders(array_keys($rows[0]));
        foreach (['name', 'guardian', 'phone'] as $required) {
            if (!isset($map[$required])) {
                throw new RuntimeException("Coluna obrigatória não encontrada: {$required}.");
            }
        }

        $month = $options['month'] ?? null;
        if ($month === null && isset($map['monthly']) && ($m = $this->parser->monthFromHeader($map['monthly'])) !== null) {
            $month = sprintf('%04d-%02d', (int) date('Y'), $m);
        }

        $records = [];
        foreach ($rows as $i => $row) {
            $records[] = $this->parser->parse($row, $map, $i + 2); // +2: cabeçalho + base 1
        }

        return $this->import($records, $month, (bool) ($options['dry_run'] ?? false), (bool) ($options['open_unpaid'] ?? false), $map);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    private function import(array $records, ?string $month, bool $dryRun, bool $openUnpaid, array $map): array
    {
        $report = [
            'dry_run' => $dryRun,
            'month' => $month,
            'columns' => $map,
            'athletes_created' => 0,
            'athletes_skipped' => 0,
            'guardians_created' => 0,
            'guardians_reused' => 0,
            'invoices' => 0,
            'payments' => 0,
            'uniform_orders' => 0,
            'sponsorships' => 0,
            'courtesies' => 0,
            'partial_payments' => [],
            'lines' => [],
        ];

        $positionIds = array_column($this->positions->all(), 'id', 'name');
        $plansByDays = [];
        foreach ($this->plans->all(true) as $plan) {
            $plansByDays[$plan['days_per_week']] ??= $plan;
        }
        $monthStart = $month !== null ? $month . '-01' : null;
        $sponsorshipValues = [];

        $this->db->pdo()->beginTransaction();
        try {
            foreach ($records as $rec) {
                $line = ['line' => $rec['line'], 'name' => $rec['name'] ?? null, 'status' => '', 'issues' => $rec['issues']];

                if ($rec['skip']) {
                    $line['status'] = 'ignorada';
                    $report['lines'][] = $line;
                    continue;
                }

                if ($this->athletes->findIdByNameAndBirth($rec['name'], $rec['birth_date']) !== null) {
                    $line['status'] = 'já cadastrado (ignorado)';
                    $report['athletes_skipped']++;
                    $report['lines'][] = $line;
                    continue;
                }

                // Responsável: reaproveita por telefone e depois por nome (irmãos).
                $guardianId = null;
                foreach ($rec['phones'] as $phone) {
                    $guardianId ??= $this->guardians->findIdByPhone($phone);
                }
                $guardianId ??= $this->guardians->findIdByName($rec['guardian']);
                if ($guardianId === null) {
                    $guardianId = $this->guardians->create(['name' => $rec['guardian'], 'notes' => self::NOTE]);
                    $this->guardians->syncPhones($guardianId, array_map(static fn ($p) => ['phone' => $p], $rec['phones']));
                    $report['guardians_created']++;
                } else {
                    $existing = array_column($this->guardians->find($guardianId)['phones'] ?? [], null, 'phone');
                    $merged = array_values($existing);
                    foreach ($rec['phones'] as $phone) {
                        if (!isset($existing[$phone])) {
                            $merged[] = ['phone' => $phone, 'is_whatsapp' => true, 'label' => null];
                        }
                    }
                    $this->guardians->syncPhones($guardianId, $merged);
                    $report['guardians_reused']++;
                    $line['issues'][] = 'Responsável já cadastrado — vinculado ao existente (irmão?).';
                }

                $enrollment = $rec['enrollment_date'] ?? $monthStart ?? date('Y-m-d');
                if ($monthStart !== null && $enrollment > $monthStart) {
                    $enrollment = $monthStart;
                }

                $athleteId = $this->athletes->create([
                    'name' => $rec['name'],
                    'birth_date' => $rec['birth_date'],
                    'guardian_id' => $guardianId,
                    'has_health_condition' => $rec['health'] !== null,
                    'health_condition' => $rec['health'],
                    'status' => 'ativo',
                    'enrollment_date' => $enrollment,
                    'notes' => self::NOTE . ' em ' . date('d/m/Y') . '.',
                ]);
                $report['athletes_created']++;

                $this->athletes->syncPositions(
                    $athleteId,
                    array_values(array_filter(array_map(static fn ($n) => $positionIds[$n] ?? null, $rec['positions']))),
                );

                $isCourtesy = ($rec['monthly']['type'] ?? null) === 'courtesy';
                $plan = $rec['plan_days'] !== null ? ($plansByDays[$rec['plan_days']] ?? null) : null;
                if ($rec['plan_days'] !== null && $plan === null) {
                    $line['issues'][] = "Nenhum plano ativo com {$rec['plan_days']} dia(s) por semana.";
                }

                $athletePlanId = null;
                if ($plan !== null) {
                    $athletePlanId = $this->athletePlans->create([
                        'athlete_id' => $athleteId,
                        'plan_id' => $plan['id'],
                        'start_date' => $enrollment,
                        'discount_type' => $isCourtesy ? 'cortesia' : 'nenhum',
                        'discount_value' => '0.00',
                    ]);
                    if ($isCourtesy) {
                        $report['courtesies']++;
                        $line['issues'][] = 'Cortesia: plano marcado como cortesia (100% de desconto).';
                    }
                }

                // Mensalidade do mês da planilha.
                if ($monthStart !== null && $plan !== null && ($rec['monthly'] !== null || $openUnpaid)) {
                    $this->importInvoice($athleteId, $athletePlanId, $plan, $rec, $monthStart, $report, $line);
                }

                // Uniforme pago.
                if (($rec['uniform']['type'] ?? null) === 'paid' && $rec['uniform']['amount'] !== null) {
                    $this->importUniform($athleteId, $rec['uniform']['amount'], $monthStart ?? $enrollment, $report);
                }

                // Patrocínio destinado ao atleta.
                if ($rec['sponsorship'] !== null) {
                    $this->sponsorships->create([
                        'athlete_id' => $athleteId,
                        'amount' => $rec['sponsorship'],
                        'received_at' => $monthStart ?? $enrollment,
                        'description' => self::NOTE,
                    ]);
                    $report['sponsorships']++;
                    if (isset($sponsorshipValues[$rec['sponsorship']])) {
                        $line['issues'][] = "Patrocínio de R$ {$rec['sponsorship']} repetido (também na linha {$sponsorshipValues[$rec['sponsorship']]}) — confira se não é o mesmo valor lançado duas vezes.";
                    }
                    $sponsorshipValues[$rec['sponsorship']] ??= $rec['line'];
                }

                $line['status'] = 'importado';
                $report['lines'][] = $line;
            }

            if ($dryRun) {
                $this->db->pdo()->rollBack();
            } else {
                $this->audit->log(null, 'spreadsheet_imported', 'athlete', null, [
                    'athletes' => $report['athletes_created'],
                    'month' => $month,
                ]);
                $this->db->pdo()->commit();
            }
        } catch (\Throwable $e) {
            if ($this->db->pdo()->inTransaction()) {
                $this->db->pdo()->rollBack();
            }
            throw $e;
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $rec
     * @param array<string, mixed> $report
     * @param array<string, mixed> $line
     */
    private function importInvoice(int $athleteId, ?int $athletePlanId, array $plan, array $rec, string $monthStart, array &$report, array &$line): void
    {
        $fee = Money::of((string) $plan['monthly_fee']);
        $monthly = $rec['monthly'];
        $isCourtesy = ($monthly['type'] ?? null) === 'courtesy';
        $discount = $isCourtesy ? $fee : Money::zero();
        $final = $fee->subtract($discount);

        $paid = Money::zero();
        if (($monthly['type'] ?? null) === 'paid') {
            $paid = $monthly['amount'] !== null ? Money::of($monthly['amount']) : $final;
        }

        $status = match (true) {
            $isCourtesy => 'cortesia',
            $paid->isZero() => 'aberta',
            $paid->cents >= $final->cents => 'paga',
            default => 'parcial',
        };
        if ($status === 'parcial') {
            $report['partial_payments'][] = ['line' => $rec['line'], 'name' => $rec['name'], 'paid' => $paid->toDecimal(), 'fee' => $final->toDecimal()];
            $line['issues'][] = "Mensalidade parcial: pago {$paid->format()} de {$final->format()} (plano {$plan['name']}).";
        }

        $invoiceId = $this->invoices->create([
            'athlete_id' => $athleteId,
            'athlete_plan_id' => $athletePlanId,
            'reference_month' => $monthStart,
            'amount' => $fee->toDecimal(),
            'discount' => $discount->toDecimal(),
            'final_amount' => $final->toDecimal(),
            'paid_amount' => $paid->toDecimal(),
            'due_date' => (new DateTimeImmutable($monthStart))->modify('+9 days')->format('Y-m-d'),
            'status' => $status,
            'notes' => self::NOTE,
        ]);
        $report['invoices']++;

        if (!$paid->isZero()) {
            $this->payments->create([
                'invoice_id' => $invoiceId,
                'amount' => $paid->toDecimal(),
                'paid_at' => $monthStart,
                'method' => 'dinheiro',
                'notes' => self::NOTE . ' (forma de pagamento não informada)',
            ]);
            $report['payments']++;
        }
    }

    /** @param array<string, mixed> $report */
    private function importUniform(int $athleteId, string $amount, string $date, array &$report): void
    {
        $itemId = $this->uniforms->findItemIdByName(self::UNIFORM_ITEM)
            ?? $this->uniforms->createItem(self::UNIFORM_ITEM, '0.00');

        $orderId = $this->uniforms->createOrder([
            'athlete_id' => $athleteId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
            'paid_amount' => $amount,
            'status' => 'pago',
            'ordered_at' => $date,
            'notes' => self::NOTE,
        ]);
        $this->payments->create([
            'uniform_order_id' => $orderId,
            'amount' => $amount,
            'paid_at' => $date,
            'method' => 'dinheiro',
            'notes' => self::NOTE,
        ]);
        $report['uniform_orders']++;
        $report['payments']++;
    }
}
