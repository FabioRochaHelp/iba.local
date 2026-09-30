<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;

/** Cortesia/bolsa integral: 100% da mensalidade. */
final class CourtesyDiscount implements DiscountPolicyInterface
{
    public function supports(string $type): bool
    {
        return $type === 'cortesia';
    }

    public function discount(Money $fee, string $value): Money
    {
        return $fee;
    }
}
