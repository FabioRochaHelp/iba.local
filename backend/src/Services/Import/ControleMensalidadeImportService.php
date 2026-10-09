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
use App\Services\Finance\InvoiceStatus;
use App\Services\Plans\PlanAssignmentService;
use App\Support\Money;
use RuntimeException;

/**
 * Carga da planilha "CONTROLE MENSALIDADE" (abas "CONTROLE DE ALUNOS" e
 * "Mensalidades 2026").
 *
 * - Atletas existentes: só completa campos vazios; plano diferente vira
 *   troca de plano a partir de PLAN_CHANGE_DATE.
 * - Atletas novos: criados com responsável e plano.
 * - Financeiro: a planilha é a fonte da verdade — as mensalidades e
 *   pagamentos de REBUILD_FROM..REBUILD_TO são apagados e recriados; cada
 *   célula com valor vira mensalidade PAGA (valor da célula = valor do mês).
 *   Meses vazios não geram cobrança.
 *
 * Tudo em uma transação; em simulação (dry run) nada é gravado.
 */
class ControleMensalidadeImportService
{
    public const SHEET_ROSTER = 'CONTROLE DE ALUNOS';
    public const SHEET_PAYMENTS = 'Mensalidades 2026';
    private const PAYMENTS_HEADER_ROW = 6;
    private const REBUILD_FROM = '2026-04-01';
    private const REBUILD_TO = '2026-09-01';
    private const PLAN_CHANGE_DATE = '2026-10-01';
    private const NOTE = 'Importado de CONTROLE MENSALIDADE.xlsx';
    private const PAYMENT_NOTE = 'Importado de CONTROLE MENSALIDADE.xlsx (data/forma de pagamento não informadas)';
    private const MONTHS = ['jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12];

    public function __construct(
        private XlsxReader $reader,
        private SpreadsheetRowParser $parser,
        private AthleteNameMatcher $matcher,
        private Connection $db,
        private AthleteRepositoryInterface $athletes,
        private GuardianRepositoryInterface $guardians,
        private PositionRepositoryInterface $positions,
        private PlanRepositoryInterface $plans,
        private AthletePlanRepositoryInterface $athletePlans,
        private PlanAssignmentService $planAssignment,
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private UniformRepositoryInterface $uniforms,
        private SponsorshipRepositoryInterface $sponsorships,
        private AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> relatório */
    public function import(string $path, bool $dryRun): array
    {
        $people = $this->loadPeople($path);
        $report = $this->emptyReport($dryRun, $people);

        try {
            $this->db->transaction(function () use ($people, &$report, $dryRun): void {
                $this->apply($people, $report);
                if ($dryRun) {
                    throw new DryRunRollback();
                }
            });
        } catch (DryRunRollback) {
            // simulação: transação desfeita
        }

        return $report;
    }

    // ------------------------------------------------------------------
    // Leitura e reconciliação das duas abas
    // ------------------------------------------------------------------

    /** @return array{people: list<array<string, mixed>>, months: list<string>, sheetTotals: array<string, string>, matches: list<array<string, mixed>>, ambiguous: list<string>, duplicates: list<string>} */
    private function loadPeople(string $path): array
    {
        $sheets = $this->reader->sheetNames($path);
        foreach ([self::SHEET_ROSTER, self::SHEET_PAYMENTS] as $required) {
            if (!in_array($required, $sheets, true)) {
                throw new RuntimeException("Aba \"{$required}\" não encontrada. Abas do arquivo: " . implode(', ', $sheets));
            }
        }

        // Aba de alunos: mesmo formato do formulário (reaproveita o parser).
        $rosterRows = $this->reader->readSheet($path, self::SHEET_ROSTER, 1);
        $map = $this->parser->mapHeaders(array_keys($rosterRows[0] ?? []));
        $people = [];
        foreach ($rosterRows as $i => $row) {
            $rec = $this->parser->parse($row, $map, $i + 2);
            if ($rec['skip']) {
                continue;
            }
            $rec['raw_birth'] = trim((string) ($row[$map['birth_date'] ?? ''] ?? ''));
            $rec['plan_text'] = trim((string) ($row[$map['plan'] ?? ''] ?? ''));
            $rec['payments'] = [];
            $rec['source'] = 'alunos';
            $people[] = $rec;
        }

        // Aba de mensalidades: cabeçalho na linha 6; colunas "abr/26"...
        $payRows = $this->reader->readSheet($path, self::SHEET_PAYMENTS, self::PAYMENTS_HEADER_ROW);
        $monthCols = [];
        foreach (array_keys($payRows[0] ?? []) as $header) {
            if (preg_match('#^([a-z]{3})/(\d{2})$#i', trim($header), $m) && isset(self::MONTHS[strtolower($m[1])])) {
                $monthCols[$header] = sprintf('20%s-%02d', $m[2], self::MONTHS[strtolower($m[1])]);
            }
        }
        if ($monthCols === []) {
            throw new RuntimeException('Colunas de mês (abr/26, mai/26...) não encontradas na aba de mensalidades.');
        }

        // Coleta as linhas da aba de mensalidades (removendo linhas repetidas idênticas).
        $sheetTotals = array_fill_keys(array_values($monthCols), Money::zero());
        $payRecords = [];
        $seen = [];
        $duplicates = [];
        foreach ($payRows as $i => $row) {
            $name = trim((string) ($row['NOME COMPLETO'] ?? ''));
            if ($name === '') {
                continue; // linhas vazias e rodapé ("Saldo Total")
            }
            $line = $i + self::PAYMENTS_HEADER_ROW + 1;
            $payments = [];
            foreach ($monthCols as $header => $ym) {
                $raw = trim((string) ($row[$header] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if (preg_match('/^corte[sz]ia/i', $raw)) {
                    $payments[$ym] = 'cortesia';
                } elseif (is_numeric(str_replace(',', '.', $raw)) && (float) str_replace(',', '.', $raw) > 0) {
                    $payments[$ym] = Money::of(str_replace(',', '.', $raw))->toDecimal();
                }
            }
            $key = AthleteNameMatcher::normalize($name);
            if (isset($seen[$key]) && $payRecords[$seen[$key]]['payments'] === $payments) {
                $duplicates[] = "\"{$name}\": linha {$line} idêntica à linha {$payRecords[$seen[$key]]['line']} — ignorada (provável linha copiada; o Saldo da planilha soma as duas).";
                continue;
            }
            foreach ($payments as $ym => $v) {
                if ($v !== 'cortesia') {
                    $sheetTotals[$ym] = $sheetTotals[$ym]->add(Money::of($v));
                }
            }
            $seen[$key] ??= count($payRecords);
            $birth = $this->birthFromCell((string) ($row['CATEGORIA'] ?? ''));
            $payRecords[] = ['name' => $name, 'line' => $line, 'payments' => $payments, 'birth' => $birth];
        }

        // Casa com a aba de alunos: primeiro só nomes exatos, depois aproximações.
        $candidates = [];
        foreach ($people as $k => $p) {
            $candidates[$k] = ['name' => $p['name'], 'birth_date' => $p['birth_date']];
        }
        $matches = [];
        $ambiguous = [];
        $assigned = [];
        foreach ([true, false] as $strict) {
            foreach ($payRecords as $r => $rec) {
                if (isset($assigned[$r])) {
                    continue;
                }
                $hit = $this->matcher->match($rec['name'], $rec['birth']['date'], $candidates, $strict);
                if ($hit['key'] !== null) {
                    $k = $hit['key'];
                    $assigned[$r] = true;
                    unset($candidates[$k]); // cada aluno casa uma vez só
                    $people[$k]['payments'] = $rec['payments'];
                    $people[$k]['birth_date'] ??= $rec['birth']['date'];
                    $people[$k]['birth_year'] = $people[$k]['birth_year'] ?? $rec['birth']['year'];
                    $people[$k]['source'] = 'ambas';
                    if ($hit['rule'] !== 'exato') {
                        $matches[] = ['planilha' => 'mensalidades', 'nome' => $rec['name'], 'casou_com' => $people[$k]['name'], 'regra' => $hit['rule']];
                    }
                } elseif (!$strict && $hit['ambiguous'] !== []) {
                    $ambiguous[] = "Mensalidades linha {$rec['line']}: \"{$rec['name']}\" casa com mais de um aluno ("
                        . implode(', ', array_map(static fn ($k) => $people[$k]['name'], $hit['ambiguous'])) . ') — criado separado, confira.';
                }
            }
        }

        // Linhas da aba de mensalidades sem aluno correspondente.
        foreach ($payRecords as $r => $rec) {
            if (isset($assigned[$r])) {
                continue;
            }
            $new = $this->parser->parse(['Nome do atleta:' => $rec['name']], ['name' => 'Nome do atleta:'], $rec['line']);
            $new['birth_date'] = $rec['birth']['date'];
            $new['birth_year'] = $rec['birth']['year'];
            $new['raw_birth'] = '';
            $new['plan_text'] = '';
            $new['plan_days'] = null;
            $new['payments'] = $rec['payments'];
            $new['source'] = 'mensalidades';
            $new['issues'] = ['Atleta só na aba de mensalidades: sem responsável, telefone e plano — complete o cadastro.'];
            $people[] = $new;
        }

        // Ano de nascimento sem dia/mês ("2014") na aba de alunos.
        foreach ($people as &$p) {
            if ($p['birth_date'] === null && preg_match('/^(19|20)\d{2}$/', (string) $p['raw_birth'])) {
                $p['birth_year'] = (int) $p['raw_birth'];
            }
            $p['birth_year'] ??= null;
        }
        unset($p);

        return [
            'people' => $people,
            'months' => array_values($monthCols),
            'sheetTotals' => array_map(static fn (Money $m) => $m->toDecimal(), $sheetTotals),
            'matches' => $matches,
            'ambiguous' => $ambiguous,
            'duplicates' => $duplicates,
        ];
    }

    /** @return array{date: ?string, year: ?int} */
    private function birthFromCell(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && $raw <= date('Y-m-d')) {
            return ['date' => $raw, 'year' => (int) substr($raw, 0, 4)];
        }
        if (preg_match('/^(19|20)\d{2}$/', $raw)) {
            return ['date' => null, 'year' => (int) $raw];
        }

        return ['date' => null, 'year' => null];
    }

    // ------------------------------------------------------------------
    // Gravação
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $report
     */
    private function apply(array $data, array &$report): void
    {
        $positionIds = array_column($this->positions->all(), 'id', 'name');
        $plansByDays = [];
        foreach ($this->plans->all(true) as $plan) {
            $plansByDays[$plan['days_per_week']] ??= $plan;
        }

        // Casamento com os atletas já cadastrados.
        $dbCandidates = [];
        foreach ($this->athletes->allForMatching() as $a) {
            $dbCandidates[$a['id']] = ['name' => $a['name'], 'birth_date' => $a['birth_date']];
        }

        // Primeiro só nomes exatos, depois aproximações (evita que "Ana Maria"
        // fique com o cadastro de "Ana Maria Pereira Dias").
        $dbMatch = [];
        foreach ([true, false] as $strict) {
            foreach ($data['people'] as $idx => $p) {
                if (isset($dbMatch[$idx])) {
                    continue;
                }
                $hit = $this->matcher->match($p['name'], $p['birth_date'], $dbCandidates, $strict);
                if ($hit['key'] !== null) {
                    $dbMatch[$idx] = (int) $hit['key'];
                    unset($dbCandidates[$hit['key']]);
                    if ($hit['rule'] !== 'exato') {
                        $report['name_matches'][] = ['planilha' => 'cadastro', 'nome' => $p['name'], 'casou_com' => "#{$hit['key']}", 'regra' => $hit['rule']];
                    }
                } elseif (!$strict && $hit['ambiguous'] !== []) {
                    $report['ambiguous'][] = "\"{$p['name']}\" lembra mais de um atleta já cadastrado (IDs " . implode(', ', $hit['ambiguous']) . ') — criado como novo, confira.';
                }
            }
        }

        $resolved = []; // índice da pessoa => athlete_id
        foreach ($data['people'] as $idx => $p) {
            if (isset($dbMatch[$idx])) {
                $athleteId = $dbMatch[$idx];
                $this->updateExisting($athleteId, $p, $positionIds, $plansByDays, $report);
            } else {
                $athleteId = $this->createAthlete($p, $positionIds, $plansByDays, $report);
            }
            $resolved[$idx] = $athleteId;
            $this->extras($athleteId, $p, $report);
        }
        $report['db_not_in_sheet'] = array_values(array_map(static fn ($c) => $c['name'], $dbCandidates));

        // Financeiro: apaga abr–set e recria a partir da planilha.
        $report['finance']['payments_removed'] = $this->payments->deleteForInvoiceMonths(self::REBUILD_FROM, self::REBUILD_TO);
        $report['finance']['invoices_removed'] = $this->invoices->deleteMonths(self::REBUILD_FROM, self::REBUILD_TO);

        foreach ($data['people'] as $idx => $p) {
            foreach ($p['payments'] as $ym => $value) {
                $this->createPaidInvoice($resolved[$idx], $p['name'], $ym, $value, $report);
            }
        }

        $months = $data['months'];
        $recorded = $this->invoices->paidTotalsByMonth($months[0] . '-01', end($months) . '-01');
        foreach ($months as $ym) {
            $report['totals'][$ym] = ['planilha' => $data['sheetTotals'][$ym] ?? '0.00', 'gravado' => $recorded[$ym] ?? '0.00'];
        }

        $this->audit->log(null, 'controle_mensalidade_imported', 'athlete', null, [
            'athletes_created' => $report['athletes_created'],
            'athletes_updated' => $report['athletes_updated'],
            'invoices_created' => $report['finance']['invoices_created'],
            'invoices_removed' => $report['finance']['invoices_removed'],
            'payments_removed' => $report['finance']['payments_removed'],
        ]);
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, int> $positionIds
     * @param array<int, array<string, mixed>> $plansByDays
     * @param array<string, mixed> $report
     */
    private function createAthlete(array $p, array $positionIds, array $plansByDays, array &$report): int
    {
        $guardianId = $this->resolveGuardian($p, $report);
        $firstPaid = $p['payments'] === [] ? null : min(array_keys($p['payments'])) . '-01';
        $enrollment = $firstPaid !== null && $firstPaid < self::REBUILD_FROM ? $firstPaid : self::REBUILD_FROM;

        $notes = self::NOTE . ' em ' . date('d/m/Y') . '.';
        if ($p['birth_date'] === null && $p['birth_year']) {
            $notes .= " Ano de nascimento informado: {$p['birth_year']} (sem dia/mês).";
        }

        $id = $this->athletes->create([
            'name' => $p['name'],
            'birth_date' => $p['birth_date'],
            'guardian_id' => $guardianId,
            'has_health_condition' => $p['health'] !== null,
            'health_condition' => $p['health'],
            'status' => 'ativo',
            'enrollment_date' => $enrollment,
            'notes' => $notes,
        ]);
        $this->athletes->syncPositions($id, $this->positionIdsFor($p, $positionIds));

        $plan = $this->planFor($p, $plansByDays);
        if ($plan !== null) {
            $this->athletePlans->create(['athlete_id' => $id, 'start_date' => $enrollment] + $plan);
        } else {
            $report['attention'][] = "{$p['name']}: sem plano definido — defina na ficha do atleta.";
        }

        $report['athletes_created']++;
        if ($p['payments'] === []) {
            $report['without_payments'][] = $p['name'];
        }
        foreach ($p['issues'] as $issue) {
            $resolvedBirth = $p['birth_date'] !== null && str_contains($issue, 'nascimento'); // data veio da aba de mensalidades
            if (!str_contains($issue, 'Situação de mensalidade') && !$resolvedBirth) { // coluna "Mensalidade Abril" é ignorada
                $report['attention'][] = "{$p['name']}: {$issue}";
            }
        }

        return $id;
    }

    /**
     * Completa somente campos vazios do atleta já cadastrado.
     *
     * @param array<string, mixed> $p
     * @param array<string, int> $positionIds
     * @param array<int, array<string, mixed>> $plansByDays
     * @param array<string, mixed> $report
     */
    private function updateExisting(int $athleteId, array $p, array $positionIds, array $plansByDays, array &$report): void
    {
        $current = $this->athletes->find($athleteId);
        if ($current === null) {
            return;
        }
        $changes = [];
        $fields = [];
        if ($current['birth_date'] === null && $p['birth_date'] !== null) {
            $fields['birth_date'] = $p['birth_date'];
            $changes[] = 'nascimento';
        }
        if (!$current['has_health_condition'] && $p['health'] !== null) {
            $fields['has_health_condition'] = true;
            $fields['health_condition'] = $p['health'];
            $changes[] = 'condição de saúde';
        }
        if ($fields !== []) {
            $this->athletes->update($athleteId, $fields);
        }
        if ($current['positions'] === [] && ($ids = $this->positionIdsFor($p, $positionIds)) !== []) {
            $this->athletes->syncPositions($athleteId, $ids);
            $changes[] = 'posições';
        }

        // Telefones novos entram no responsável já cadastrado.
        $guardian = $this->guardians->find((int) $current['guardian']['id']);
        if ($guardian !== null) {
            $existing = array_column($guardian['phones'], null, 'phone');
            $merged = array_values($existing);
            foreach ($p['phones'] as $phone) {
                if (!isset($existing[$phone])) {
                    $merged[] = ['phone' => $phone, 'is_whatsapp' => true, 'label' => null];
                }
            }
            if (count($merged) !== count($existing)) {
                $this->guardians->syncPhones((int) $guardian['id'], $merged);
                $changes[] = 'telefone do responsável';
            }
        }

        // Plano diferente na planilha: troca vale a partir de PLAN_CHANGE_DATE.
        // Descontos especiais já definidos no sistema (cortesia/percentual) são mantidos.
        $plan = $this->planFor($p, $plansByDays);
        $currentPlan = $this->athletePlans->current($athleteId);
        if ($plan !== null) {
            $special = $currentPlan !== null && in_array($currentPlan['discount_type'], ['cortesia', 'percentual'], true);
            $planChanged = $currentPlan === null || (int) $currentPlan['plan_id'] !== $plan['plan_id'];
            $priceChanged = $currentPlan !== null && $plan['discount_type'] === 'valor'
                && ($currentPlan['discount_type'] !== 'valor' || !Money::of((string) $currentPlan['discount_value'])->equals(Money::of($plan['discount_value'])));
            if ($planChanged || ($priceChanged && !$special)) {
                if ($special && $currentPlan !== null) {
                    $plan['discount_type'] = $currentPlan['discount_type'];
                    $plan['discount_value'] = (string) $currentPlan['discount_value'];
                }
                $this->planAssignment->assign($athleteId, $plan + ['start_date' => self::PLAN_CHANGE_DATE]);
                $changes[] = 'plano (a partir de ' . date('d/m/Y', (int) strtotime(self::PLAN_CHANGE_DATE)) . ')';
            }
        }

        if ($changes !== []) {
            $report['athletes_updated']++;
            $report['updated_details'][] = "{$p['name']}: " . implode(', ', $changes);
        } else {
            $report['athletes_unchanged']++;
        }
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $report
     */
    private function resolveGuardian(array $p, array &$report): int
    {
        $guardianId = null;
        foreach ($p['phones'] as $phone) {
            $guardianId ??= $this->guardians->findIdByPhone($phone);
        }
        if ($guardianId === null && !str_starts_with($p['guardian'], 'Responsável de ')) {
            $guardianId = $this->guardians->findIdByName($p['guardian']);
        }
        if ($guardianId !== null) {
            $report['guardians_reused']++;

            return $guardianId;
        }

        $guardianId = $this->guardians->create(['name' => $p['guardian'], 'notes' => self::NOTE]);
        $this->guardians->syncPhones($guardianId, array_map(static fn ($ph) => ['phone' => $ph], $p['phones']));
        $report['guardians_created']++;

        return $guardianId;
    }

    /**
     * @param array<string, mixed> $p
     * @param array<int, array<string, mixed>> $plansByDays
     * @return array{plan_id: int, discount_type: string, discount_value: string}|null
     */
    private function planFor(array $p, array $plansByDays): ?array
    {
        $plan = $p['plan_days'] !== null ? ($plansByDays[$p['plan_days']] ?? null) : null;
        if ($plan === null) {
            return null;
        }
        $type = 'nenhum';
        $value = '0.00';
        // "2 dias ... R$ 90,00/mês" num plano de R$ 110 => desconto fixo de R$ 20.
        if (preg_match('/R\$\s*([\d.]+,\d{2}|\d+)/u', (string) $p['plan_text'], $m)) {
            $price = Money::of($m[1]);
            $fee = Money::of((string) $plan['monthly_fee']);
            if ($price->cents > 0 && $price->cents < $fee->cents) {
                $type = 'valor';
                $value = $fee->subtract($price)->toDecimal();
            }
        }

        return ['plan_id' => (int) $plan['id'], 'discount_type' => $type, 'discount_value' => $value];
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, int> $positionIds
     * @return list<int>
     */
    private function positionIdsFor(array $p, array $positionIds): array
    {
        return array_values(array_filter(array_map(static fn ($n) => $positionIds[$n] ?? null, $p['positions'])));
    }

    /** @param array<string, mixed> $report */
    private function createPaidInvoice(int $athleteId, string $name, string $ym, string $value, array &$report): void
    {
        $monthStart = $ym . '-01';
        if ($monthStart > self::REBUILD_TO && $this->invoices->findByAthleteAndMonth($athleteId, $monthStart) !== null) {
            $report['attention'][] = "{$name}: já existe mensalidade de {$ym} no sistema — mantida, valor da planilha ignorado.";

            return;
        }

        $ap = $this->athletePlans->activeOn($athleteId, $monthStart) ?? $this->athletePlans->current($athleteId);
        $fee = $ap !== null ? Money::of((string) $ap['monthly_fee']) : null;
        $notes = self::NOTE;

        if ($value === 'cortesia') {
            $amount = $fee ?? Money::zero();
            $discount = $amount;
            $paid = Money::zero();
        } else {
            $paid = Money::of($value);
            if ($fee !== null && $paid->cents < $fee->cents) {
                $amount = $fee;
                $discount = $fee->subtract($paid);
            } else {
                $amount = $paid;
                $discount = Money::zero();
                if ($fee !== null && $paid->cents > $fee->cents) {
                    $notes = "Valor acima do plano ({$fee->format()}) — conferir (meses acumulados?). " . self::NOTE;
                    $report['above_plan'][] = "{$name} — {$ym}: {$paid->format()} (plano {$fee->format()})";
                }
            }
        }
        $final = $amount->subtract($discount);

        $invoiceId = $this->invoices->create([
            'athlete_id' => $athleteId,
            'athlete_plan_id' => $ap !== null ? (int) $ap['id'] : null,
            'reference_month' => $monthStart,
            'amount' => $amount->toDecimal(),
            'discount' => $discount->toDecimal(),
            'final_amount' => $final->toDecimal(),
            'paid_amount' => $paid->toDecimal(),
            'due_date' => $ym . '-10',
            'status' => InvoiceStatus::resolve($final, $paid),
            'notes' => mb_substr($notes, 0, 255),
        ]);
        $report['finance']['invoices_created']++;

        if ($paid->cents > 0) {
            $this->payments->create([
                'invoice_id' => $invoiceId,
                'amount' => $paid->toDecimal(),
                'paid_at' => $ym . '-10' <= date('Y-m-d') ? $ym . '-10' : date('Y-m-d'),
                'method' => 'dinheiro',
                'notes' => self::PAYMENT_NOTE,
            ]);
            $report['finance']['payments_created']++;
        } else {
            $report['finance']['courtesies']++;
        }
    }

    /**
     * Uniforme e patrocínio informados na aba de alunos (sem duplicar o que já existe).
     *
     * @param array<string, mixed> $p
     * @param array<string, mixed> $report
     */
    private function extras(int $athleteId, array $p, array &$report): void
    {
        $uniform = $p['uniform'] ?? null;
        if (($uniform['type'] ?? null) === 'paid' && $uniform['amount'] !== null) {
            $orders = $this->uniforms->paginateOrders(['athlete_id' => $athleteId], 1, 50)['items'];
            if (array_filter($orders, static fn ($o) => $o['total'] === $uniform['amount']) === []) {
                $itemId = $this->uniforms->findItemIdByName(AthleteImportService::UNIFORM_ITEM)
                    ?? $this->uniforms->createItem(AthleteImportService::UNIFORM_ITEM, '0.00');
                $orderId = $this->uniforms->createOrder([
                    'athlete_id' => $athleteId, 'item_id' => $itemId, 'quantity' => 1,
                    'unit_price' => $uniform['amount'], 'total' => $uniform['amount'], 'paid_amount' => $uniform['amount'],
                    'status' => 'pago', 'ordered_at' => self::REBUILD_FROM, 'notes' => self::NOTE,
                ]);
                $this->payments->create([
                    'uniform_order_id' => $orderId, 'amount' => $uniform['amount'], 'paid_at' => self::REBUILD_FROM,
                    'method' => 'dinheiro', 'notes' => self::PAYMENT_NOTE,
                ]);
                $report['uniforms_created'][] = "{$p['name']}: R$ {$uniform['amount']}";
            }
        }

        if (($p['sponsorship'] ?? null) !== null) {
            $existing = $this->sponsorships->paginate(['athlete_id' => $athleteId], 1, 50)['items'];
            if (array_filter($existing, static fn ($e) => $e['amount'] === $p['sponsorship']) === []) {
                $this->sponsorships->create([
                    'athlete_id' => $athleteId, 'amount' => $p['sponsorship'], 'received_at' => self::REBUILD_FROM,
                    'method' => 'pix', 'description' => self::NOTE,
                ]);
                $report['sponsorships_created'][] = "{$p['name']}: R$ {$p['sponsorship']}";
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function emptyReport(bool $dryRun, array $data): array
    {
        return [
            'dry_run' => $dryRun,
            'people_in_sheet' => count($data['people']),
            'only_payments_sheet' => array_values(array_map(static fn ($p) => $p['name'], array_filter($data['people'], static fn ($p) => $p['source'] === 'mensalidades'))),
            'name_matches' => $data['matches'],
            'ambiguous' => $data['ambiguous'],
            'duplicates' => $data['duplicates'],
            'athletes_created' => 0,
            'athletes_updated' => 0,
            'athletes_unchanged' => 0,
            'updated_details' => [],
            'guardians_created' => 0,
            'guardians_reused' => 0,
            'without_payments' => [],
            'db_not_in_sheet' => [],
            'finance' => ['invoices_removed' => 0, 'payments_removed' => 0, 'invoices_created' => 0, 'payments_created' => 0, 'courtesies' => 0],
            'totals' => [],
            'above_plan' => [],
            'uniforms_created' => [],
            'sponsorships_created' => [],
            'attention' => [],
        ];
    }
}
