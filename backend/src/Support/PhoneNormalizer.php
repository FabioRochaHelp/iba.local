<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normaliza telefones brasileiros digitados livremente:
 * "(18)99990-0101", "18 99990-0202", "99990 0404", "18999901111 e (18) 999901212".
 * Resultado: somente dígitos com DDD (10 ou 11 dígitos).
 */
final class PhoneNormalizer
{
    public function __construct(private string $defaultDdd = '18')
    {
    }

    /**
     * Separa múltiplos números ("/", " e ", ",", ";") e normaliza cada um.
     *
     * @return array{valid: list<string>, invalid: list<string>}
     */
    public function parseMany(string $raw): array
    {
        $valid = [];
        $invalid = [];
        $parts = preg_split('#\s*(?:/|,|;|\be\b|\bou\b)\s*#iu', trim($raw)) ?: [];
        $ddd = $this->defaultDdd;

        foreach ($parts as $part) {
            if (trim($part) === '') {
                continue;
            }
            $phone = $this->normalize($part, $ddd);
            if ($phone === null) {
                $invalid[] = trim($part);
            } elseif (!in_array($phone, $valid, true)) {
                $valid[] = $phone;
                // "18 99990-0909 / 99990 1010": os números seguintes herdam o DDD do anterior.
                $ddd = substr($phone, 0, 2);
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    public function normalize(string $raw, ?string $ddd = null): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && in_array(strlen($digits), [11, 12], true)) {
            $digits = substr($digits, 1); // 0 + DDD
        }
        if (in_array(strlen($digits), [8, 9], true)) {
            $digits = ($ddd ?? $this->defaultDdd) . $digits;
        }

        return $this->isValid($digits) ? $digits : null;
    }

    public function isValid(string $digits): bool
    {
        if (!preg_match('/^[1-9][1-9]\d{8,9}$/', $digits)) {
            return false;
        }
        // Celular (11 dígitos) começa com 9 após o DDD.
        return strlen($digits) === 10 || $digits[2] === '9';
    }

    public static function format(string $digits): string
    {
        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits,
        };
    }
}
