<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Config\Config;
use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Repositories\Contracts\AthletePlanRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Discounts\DiscountCalculator;
use App\Support\Money;
use DateTimeImmutable;

/**
 * Gera as mensalidades do mês para os atletas ativos com plano.
 * Idempotente: quem já tem mensalidade no mês é ignorado (índice único
 * atleta+mês garante isso também no banco).
 */
class InvoiceGeneratorService
{
    public function __construct(
        private AthletePlanRepositoryInterface $athletePlans,
        private InvoiceRepositoryInterface $invoices,
        private DiscountCalculator $discounts,
        private Connection $db,
        private AuditLogger $audit,
        private Config $config,
    ) {
    }

    /**
     * @param string $month AAAA-MM
     * @return array{month: string, created: int, skipped: int, total: string}
     */
    public function generate(string $month, ?Request $request = null): array
    {
        $start = new DateTimeImmutable($month . '-01');
        $monthStart = $start->format('Y-m-d');
        $monthEnd = $start->modify('last day of this month')->format('Y-m-d');
        $dueDay = (int) $this->config->get('finance.due_day', 10);
        $dueDate = $start->setDate((int) $start->format('Y'), (int) $start->format('m'), $dueDay)->format('Y-m-d');

        return $this->db->transaction(function () use ($month, $monthStart, $monthEnd, $dueDate, $request): array {
            $already = array_flip($this->invoices->billedAthleteIds($monthStart));
            $created = 0;
            $skipped = 0;
            $total = Money::zero();

            foreach ($this->athletePlans->billable($monthStart, $monthEnd) as $row) {
                if (isset($already[$row['athlete_id']])) {
                    $skipped++;
                    continue;
                }

                $fee = Money::of($row['monthly_fee']);
                $discount = $this->discounts->discount($fee, $row['discount_type'], $row['discount_value']);
                $final = $fee->subtract($discount);

                $this->invoices->create([
                    'athlete_id' => $row['athlete_id'],
                    'athlete_plan_id' => $row['athlete_plan_id'],
                    'reference_month' => $monthStart,
                    'amount' => $fee->toDecimal(),
                    'discount' => $discount->toDecimal(),
                    'final_amount' => $final->toDecimal(),
                    'paid_amount' => '0.00',
                    'due_date' => $dueDate,
                    'status' => InvoiceStatus::resolve($final, Money::zero()),
                ]);
                $created++;
                $total = $total->add($final);
            }

            $this->audit->log($request, 'invoices_generated', 'invoice', null, [
                'month' => $month,
                'created' => $created,
                'skipped' => $skipped,
            ]);

            return ['month' => $month, 'created' => $created, 'skipped' => $skipped, 'total' => $total->toDecimal()];
        });
    }
}
