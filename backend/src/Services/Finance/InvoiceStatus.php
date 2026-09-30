<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Support\Money;

/**
 * Status de uma mensalidade a partir dos valores (fonte única da regra).
 * "Atrasada" não é status gravado: é aberta/parcial com vencimento passado.
 */
final class InvoiceStatus
{
    public const OPEN = 'aberta';
    public const PAID = 'paga';
    public const PARTIAL = 'parcial';
    public const COURTESY = 'cortesia';
    public const CANCELLED = 'cancelada';

    public static function resolve(Money $finalAmount, Money $paidAmount): string
    {
        return match (true) {
            $finalAmount->isZero() => self::COURTESY,
            $paidAmount->cents >= $finalAmount->cents => self::PAID,
            $paidAmount->cents > 0 => self::PARTIAL,
            default => self::OPEN,
        };
    }

    public static function acceptsPayment(string $status): bool
    {
        return in_array($status, [self::OPEN, self::PARTIAL], true);
    }
}
