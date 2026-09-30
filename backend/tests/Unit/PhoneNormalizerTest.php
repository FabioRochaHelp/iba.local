<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    /** @return list<array{string, ?string}> */
    public static function cases(): array
    {
        return [
            ['(18)99990-0101', '18999900101'],
            ['18 99990-0202', '18999900202'],
            ['18-999900303', '18999900303'],
            ['99990 0404', '18999900404'],      // sem DDD: assume 18
            ['999900505', '18999900505'],
            ['19 999900606', '19999900606'],    // outro DDD
            ['+55 (18) 99990-0707', '18999900707'],
            ['(18) 3222-1234', '1832221234'],   // fixo
            ['(18) 9999000808', null],          // dígito a mais
            ['123', null],
            ['18 89999-9999', null],            // celular sem 9
        ];
    }

    #[DataProvider('cases')]
    public function testNormalize(string $raw, ?string $expected): void
    {
        self::assertSame($expected, (new PhoneNormalizer())->normalize($raw));
    }

    public function testParseManyWithSeparatorsAndInheritedAreaCode(): void
    {
        $n = new PhoneNormalizer();

        self::assertSame(['18999900909', '18999901010'], $n->parseMany('18 99990-0909 / 99990 1010')['valid']);
        self::assertSame(['18999901111', '18999901212'], $n->parseMany('(18)999901111 e (18) 999901212')['valid']);
        self::assertSame(['21987654321', '21912345678'], $n->parseMany('21 98765-4321 / 91234-5678')['valid']);
    }

    public function testFormat(): void
    {
        self::assertSame('(18) 99990-0101', PhoneNormalizer::format('18999900101'));
    }
}
