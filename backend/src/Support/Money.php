<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Valor monetário em centavos (evita erros de ponto flutuante).
 */
final class Money
{
    private function __construct(public readonly int $cents)
    {
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    /** Aceita "110.00", "110", 110.5 ou formato brasileiro "2.050,00". */
    public static function of(string|int|float $value): self
    {
        if (is_int($value)) {
            return new self($value * 100);
        }
        if (is_float($value)) {
            return new self((int) round($value * 100));
        }

        $value = trim(str_ireplace('R$', '', $value));
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$|^-?\d+(,\d{1,2})$/', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value); // formato BR
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$value}");
        }

        return new self((int) round(((float) $value) * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function percent(float $percent): self
    {
        return new self((int) round($this->cents * $percent / 100));
    }

    public function min(self $other): self
    {
        return $this->cents <= $other->cents ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->cents >= $other->cents ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /** Formato para o banco/JSON: "110.00". */
    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function format(): string
    {
        return 'R$ ' . number_format($this->cents / 100, 2, ',', '.');
    }
}
