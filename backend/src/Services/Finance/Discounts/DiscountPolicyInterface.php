<?php

declare(strict_types=1);

namespace App\Services\Finance\Discounts;

use App\Support\Money;

/**
 * Regra de desconto (Aberto/Fechado): um novo tipo de desconto é uma
 * nova classe registrada no DiscountCalculator — o gerador de
 * mensalidades não muda.
 */
interface DiscountPolicyInterface
{
    public function supports(string $type): bool;

    public function discount(Money $fee, string $value): Money;
}
