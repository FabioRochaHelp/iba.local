<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;

final class NoDiscount implements DiscountPolicyInterface
{
    public function supports(string $type): bool
    {
        return $type === 'nenhum';
    }

    public function discount(Money $fee, string $value): Money
    {
        return Money::zero();
    }
}
