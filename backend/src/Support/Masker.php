<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mascaramento de dados pessoais em listagens (LGPD).
 */
final class Masker
{
    public static function cpf(?string $cpf): ?string
    {
        if ($cpf === null || strlen($cpf) !== 11) {
            return null;
        }

        return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
    }

    public static function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
