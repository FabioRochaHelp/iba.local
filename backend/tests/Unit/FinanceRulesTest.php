<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Finance\Discounts\DiscountCalculator;
use App\Services\Finance\InvoiceStatus;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class FinanceRulesTest extends TestCase
{
    public function testDiscountPolicies(): void
    {
        $calc = DiscountCalculator::default();
        $fee = Money::of('110.00');

        self::assertSame('0.00', $calc->discount($fee, 'nenhum', '0')->toDecimal());
        self::assertSame('110.00', $calc->discount($fee, 'cortesia', '0')->toDecimal());
        self::assertSame('11.00', $calc->discount($fee, 'percentual', '10')->toDecimal());
        self::assertSame('110.00', $calc->discount($fee, 'percentual', '150')->toDecimal(), 'percentual limitado a 100%');
        self::assertSame('30.00', $calc->discount($fee, 'valor', '30')->toDecimal());
        self::assertSame('110.00', $calc->discount($fee, 'valor', '500')->toDecimal(), 'desconto nunca maior que a mensalidade');
    }

    public function testUnknownDiscountTypeFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DiscountCalculator::default()->discount(Money::of('10'), 'irmaos', '0');
    }

    public function testStatusResolution(): void
    {
        self::assertSame('cortesia', InvoiceStatus::resolve(Money::zero(), Money::zero()));
        self::assertSame('aberta', InvoiceStatus::resolve(Money::of('110'), Money::zero()));
        self::assertSame('parcial', InvoiceStatus::resolve(Money::of('110'), Money::of('60')));
        self::assertSame('paga', InvoiceStatus::resolve(Money::of('110'), Money::of('110')));
        self::assertTrue(InvoiceStatus::acceptsPayment('parcial'));
        self::assertFalse(InvoiceStatus::acceptsPayment('cancelada'));
        self::assertFalse(InvoiceStatus::acceptsPayment('paga'));
    }
}
