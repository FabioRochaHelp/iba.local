<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Categoria por ano de nascimento (padrão do futebol de base):
 * no ano X, quem nasceu em X-10 joga no Sub-11.
 */
final class AgeCategory
{
    public static function forBirthDate(?string $birthDate, ?int $referenceYear = null): ?string
    {
        if ($birthDate === null || $birthDate === '') {
            return null;
        }
        $year = (int) substr($birthDate, 0, 4);
        $referenceYear ??= (int) date('Y');
        $sub = $referenceYear - $year + 1;

        return $sub > 0 ? 'Sub-' . $sub : null;
    }

    /** @return array{0: int, 1: int}|null  [ano mínimo, ano máximo] de nascimento */
    public static function birthYearRange(string $category, ?int $referenceYear = null): ?array
    {
        if (!preg_match('/^Sub-(\d{1,2})$/', $category, $m)) {
            return null;
        }
        $referenceYear ??= (int) date('Y');
        $year = $referenceYear - (int) $m[1] + 1;

        return [$year, $year];
    }

    public static function age(?string $birthDate, ?DateTimeImmutable $today = null): ?int
    {
        if ($birthDate === null || $birthDate === '') {
            return null;
        }
        $today ??= new DateTimeImmutable('today');

        return (new DateTimeImmutable($birthDate))->diff($today)->y;
    }
}
