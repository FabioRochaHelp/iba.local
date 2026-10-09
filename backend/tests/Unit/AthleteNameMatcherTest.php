<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\AthleteNameMatcher;
use PHPUnit\Framework\TestCase;

/** Nomes fictícios. */
final class AthleteNameMatcherTest extends TestCase
{
    private AthleteNameMatcher $m;

    /** @var array<int, array{name: string, birth_date: ?string}> */
    private array $pool = [
        1 => ['name' => 'Caio Fictício da Silva', 'birth_date' => '2014-03-01'],
        2 => ['name' => 'Davi Exemplo', 'birth_date' => null],
        3 => ['name' => 'Enzo Teste Lopes Prado', 'birth_date' => '2013-05-05'],
        4 => ['name' => 'Rafael Modelo', 'birth_date' => '2015-01-01'],
        5 => ['name' => 'Rafael Modelo Junior', 'birth_date' => '2016-01-01'],
        6 => ['name' => 'Bruno Lima Barros', 'birth_date' => null],
        7 => ['name' => 'Otavio Hugo Amostra', 'birth_date' => null],
    ];

    protected function setUp(): void
    {
        $this->m = new AthleteNameMatcher();
    }

    public function testNormalize(): void
    {
        self::assertSame('caio ficticio da silva', AthleteNameMatcher::normalize('  CAIO  Fictício da Silva '));
        self::assertSame('davi exemplo', AthleteNameMatcher::normalize('Davi Exemplo / Goleiro'));
        self::assertSame('bento joca', AthleteNameMatcher::normalize('Bento ( Joca )'));
    }

    public function testRules(): void
    {
        self::assertSame(['key' => 1, 'rule' => 'exato', 'ambiguous' => []], $this->m->match('caio ficticio da SILVA', null, $this->pool));
        self::assertSame('sem_espacos', $this->m->match('Bruno LimaBarros', null, $this->pool)['rule']);
        self::assertSame(2, $this->m->match('Davi Exemplo Nunes Paz', null, $this->pool)['key']);          // prefixo
        self::assertSame(3, $this->m->match('Enzo Teste Lopes Prado D N', '2013-05-05', $this->pool)['key']);     // prefixo inverso
        self::assertSame(7, $this->m->match('Otavvio Hugo Amostra', null, $this->pool)['key']);                   // aproximado
        self::assertSame('prefixo_aprox', $this->m->match('Enzo Testi Lopes', null, $this->pool)['rule']);
    }

    public function testStrictModeOnlyExactRules(): void
    {
        self::assertNull($this->m->match('Davi Exemplo Nunes', null, $this->pool, true)['key']);
        self::assertSame(6, $this->m->match('Bruno LimaBarros', null, $this->pool, true)['key']);
    }

    public function testBirthDateDisambiguatesAndRejects(): void
    {
        // "Rafael Modelo" casa exato com o 4; "Rafael" sozinho é prefixo de 4 e 5 -> nascimento decide
        self::assertSame(5, $this->m->match('Rafael', '2016-01-01', $this->pool)['key']);
        $amb = $this->m->match('Rafael', null, $this->pool);
        self::assertNull($amb['key']);
        self::assertSame([4, 5], $amb['ambiguous']);
        // nome aproximado mas nascimento diferente -> não casa
        self::assertNull($this->m->match('Enzo Teste Lopes', '2010-01-01', $this->pool)['key']);
    }

    public function testNoMatch(): void
    {
        self::assertNull($this->m->match('Pessoa Inexistente', null, $this->pool)['key']);
        self::assertNull($this->m->match('', null, $this->pool)['key']);
    }
}
