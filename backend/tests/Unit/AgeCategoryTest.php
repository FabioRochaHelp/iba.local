<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AgeCategory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AgeCategoryTest extends TestCase
{
    public function testCategoryByBirthYear(): void
    {
        self::assertSame('Sub-15', AgeCategory::forBirthDate('2012-06-27', 2026));
        self::assertSame('Sub-8', AgeCategory::forBirthDate('2019-04-08', 2026));
        self::assertNull(AgeCategory::forBirthDate(null, 2026));
        self::assertSame([2012, 2012], AgeCategory::birthYearRange('Sub-15', 2026));
    }

    public function testAge(): void
    {
        self::assertSame(13, AgeCategory::age('2012-10-01', new DateTimeImmutable('2026-09-29')));
        self::assertSame(14, AgeCategory::age('2012-09-29', new DateTimeImmutable('2026-09-29')));
    }
}
