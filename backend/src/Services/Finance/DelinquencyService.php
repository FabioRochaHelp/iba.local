<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\Money;

/**
 * Inadimplência agrupada por responsável (quem recebe a cobrança).
 */
class DelinquencyService
{
    public function __construct(private InvoiceRepositoryInterface $invoices)
    {
    }

    /** @return array{groups: list<array<string, mixed>>, total: string, invoices: int} */
    public function report(?string $today = null): array
    {
        $groups = [];
        $total = Money::zero();
        $count = 0;

        foreach ($this->invoices->overdue($today ?? date('Y-m-d')) as $inv) {
            $gid = $inv['guardian_id'];
            $groups[$gid] ??= [
                'guardian_id' => $gid,
                'guardian_name' => $inv['guardian_name'],
                'guardian_phone' => $inv['guardian_phone'],
                'total' => Money::zero(),
                'oldest_due_date' => $inv['due_date'],
                'invoices' => [],
            ];
            $remaining = Money::of($inv['remaining']);
            $groups[$gid]['total'] = $groups[$gid]['total']->add($remaining);
            $groups[$gid]['oldest_due_date'] = min($groups[$gid]['oldest_due_date'], $inv['due_date']);
            $groups[$gid]['invoices'][] = [
                'id' => $inv['id'],
                'athlete_id' => $inv['athlete_id'],
                'athlete_name' => $inv['athlete_name'],
                'reference_month' => $inv['reference_month'],
                'due_date' => $inv['due_date'],
                'status' => $inv['status'],
                'remaining' => $inv['remaining'],
            ];
            $total = $total->add($remaining);
            $count++;
        }

        $list = array_values($groups);
        usort($list, static fn ($a, $b) => $b['total']->cents <=> $a['total']->cents);
        foreach ($list as &$group) {
            $group['total'] = $group['total']->toDecimal();
            $group['days_overdue'] = (int) ((strtotime($today ?? date('Y-m-d')) - strtotime($group['oldest_due_date'])) / 86400);
        }

        return ['groups' => $list, 'total' => $total->toDecimal(), 'invoices' => $count];
    }
}
