<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testParsesBrazilianAndDecimalFormats(): void
    {
        self::assertSame(205000, Money::of('2.050,00')->cents);
        self::assertSame(6000, Money::of('60,00')->cents);
        self::assertSame(11000, Money::of('110.00')->cents);
        self::assertSame(11050, Money::of('R$ 110,5')->cents);
        self::assertSame('110.00', Money::of(110)->toDecimal());
    }

    public function testArithmeticWithoutFloatErrors(): void
    {
        $total = Money::of('0.10')->add(Money::of('0.20'));

        self::assertSame('0.30', $total->toDecimal());
        self::assertSame('R$ 2.050,00', Money::of('2050')->format());
        self::assertSame('55.00', Money::of('110.00')->percent(50)->toDecimal());
    }
}
