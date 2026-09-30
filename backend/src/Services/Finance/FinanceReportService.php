<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use DateTimeImmutable;

class FinanceReportService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    /** @return array<string, mixed> */
    public function monthly(string $month): array
    {
        $start = new DateTimeImmutable($month . '-01');

        return [
            'month' => $month,
            // Competência: mensalidades referentes ao mês.
            'invoices' => $this->invoices->monthSummary($start->format('Y-m-d')),
            // Caixa: pagamentos recebidos dentro do mês (de qualquer competência).
            'cash_received' => $this->payments->receivedBetween(
                $start->format('Y-m-d'),
                $start->modify('last day of this month')->format('Y-m-d'),
            ),
        ];
    }

    /** @return list<array{month: string, expected: string, received: string}> últimos N meses, com zeros */
    public function series(int $months = 6, ?string $until = null): array
    {
        $end = new DateTimeImmutable(($until ?? date('Y-m')) . '-01');
        $start = $end->modify('-' . ($months - 1) . ' months');
        $data = array_column($this->invoices->monthlySeries($start->format('Y-m-d'), $end->format('Y-m-d')), null, 'month');

        $series = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 month')) {
            $key = $d->format('Y-m');
            $series[] = $data[$key] ?? ['month' => $key, 'expected' => '0.00', 'received' => '0.00'];
        }

        return $series;
    }
}
