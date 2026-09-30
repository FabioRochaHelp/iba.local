<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;

final class PercentageDiscount implements DiscountPolicyInterface
{
    public function supports(string $type): bool
    {
        return $type === 'percentual';
    }

    public function discount(Money $fee, string $value): Money
    {
        $percent = max(0.0, min(100.0, (float) $value));

        return $fee->percent($percent);
    }
}
