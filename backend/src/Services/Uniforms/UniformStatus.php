<?php

declare(strict_types=1);

namespace App\Services\Uniforms;

use App\Support\Money;

final class UniformStatus
{
    public const PENDING = 'pendente';
    public const PARTIAL = 'pago_parcial';
    public const PAID = 'pago';
    public const CANCELLED = 'cancelado';

    public static function resolve(Money $total, Money $paid): string
    {
        return match (true) {
            $paid->cents >= $total->cents => self::PAID,
            $paid->cents > 0 => self::PARTIAL,
            default => self::PENDING,
        };
    }
}
