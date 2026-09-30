<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;
use InvalidArgumentException;

final class DiscountCalculator
{
    /** @param list<DiscountPolicyInterface> $policies */
    public function __construct(private array $policies)
    {
    }

    public static function default(): self
    {
        return new self([new NoDiscount(), new CourtesyDiscount(), new PercentageDiscount(), new FixedValueDiscount()]);
    }

    public function discount(Money $fee, string $type, string $value): Money
    {
        foreach ($this->policies as $policy) {
            if ($policy->supports($type)) {
                return $policy->discount($fee, $value)->min($fee);
            }
        }

        throw new InvalidArgumentException("Tipo de desconto desconhecido: {$type}");
    }
}
