<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;

final class FixedValueDiscount implements DiscountPolicyInterface
{
    public function supports(string $type): bool
    {
        return $type === 'valor';
    }

    public function discount(Money $fee, string $value): Money
    {
        return Money::of($value)->max(Money::zero())->min($fee);
    }
}
