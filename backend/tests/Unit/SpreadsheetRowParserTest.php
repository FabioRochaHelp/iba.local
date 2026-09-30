<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\SpreadsheetRowParser;
use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class SpreadsheetRowParserTest extends TestCase
{
    private SpreadsheetRowParser $parser;

    /** @var array<string, string> */
    private array $map;

    private const HEADERS = [
        'Nome do atleta:', 'Data de nascimento:', 'Nome do responsável:', 'Telefone de contato do responsável:',
        'Posição que joga o atleta:', 'Possui alguma condição de saúde?', 'Escolha o Plano de treinamento:',
        'Pagamento Uniforme', 'Mensalidade Abril', 'Recebi de Patrocinio',
    ];

    protected function setUp(): void
    {
        $this->parser = new SpreadsheetRowParser(new PhoneNormalizer());
        $this->map = $this->parser->mapHeaders(self::HEADERS);
    }

    /** @param list<string> $values */
    private function parse(array $values): array
    {
        return $this->parser->parse(array_combine(self::HEADERS, $values), $this->map, 2);
    }

    public function testMapsAllColumnsAndMonth(): void
    {
        self::assertSame(
            ['name', 'birth_date', 'guardian', 'phone', 'positions', 'health', 'plan', 'uniform', 'monthly', 'sponsorship'],
            array_keys($this->map),
        );
        self::assertSame(4, $this->parser->monthFromHeader('Mensalidade Abril'));
    }

    public function testFullRow(): void
    {
        $r = $this->parse([
            'pedro henrique de souza teste', '10/05/2015', 'maria de souza teste', '18 999901313',
            'Lateral Esquerdo / Meia Direita', 'Sim Asma',
            '2 dias por semana - segunda e quarta - R$ 110,00/mês', 'Pago 40,00', 'Pago 60,00', '2.050,00',
        ]);

        self::assertSame('Pedro Henrique de Souza Teste', $r['name']);
        self::assertSame('2015-05-10', $r['birth_date']);
        self::assertSame('Maria de Souza Teste', $r['guardian']);
        self::assertSame(['18999901313'], $r['phones']);
        self::assertSame(['Lateral Esquerdo', 'Meia Direita'], $r['positions']);
        self::assertSame('Asma', $r['health']);
        self::assertSame(2, $r['plan_days']);
        self::assertSame(['type' => 'paid', 'amount' => '40.00'], $r['uniform']);
        self::assertSame(['type' => 'paid', 'amount' => '60.00'], $r['monthly']);
        self::assertSame('2050.00', $r['sponsorship']);
        self::assertSame([], $r['issues']);
    }

    public function testNegativeHealthVariantsAndCourtesy(): void
    {
        foreach (['Não', 'Nao', 'não.', 'NÃO', ''] as $value) {
            $r = $this->parse(['Ana', '', 'Bia', '18999900101', 'Zagueiro', $value, '1 dia por semana', '', 'CORTEZIA', '']);
            self::assertNull($r['health'], "'{$value}' deveria ser sem condição");
            self::assertSame(['type' => 'courtesy', 'amount' => null], $r['monthly']);
        }
    }

    public function testReportsProblems(): void
    {
        $r = $this->parse(['Ana', '31/02/2015', 'Bia', '(18) 9999000808', 'Meia/Líbero', 'Não', 'x', '', 'talvez', '']);

        $issues = implode(' | ', $r['issues']);
        self::assertStringContainsString('nascimento inválida', $issues);
        self::assertStringContainsString('Telefone inválido', $issues);
        self::assertStringContainsString('Líbero', $issues);
        self::assertStringContainsString('Plano não reconhecido', $issues);
        self::assertStringContainsString('mensalidade não reconhecida', $issues);
        self::assertSame(['Meia'], $r['positions']);
    }

    public function testHtmlInCellsIsStripped(): void
    {
        $r = $this->parse(['<script>x</script>Ana', '', 'Bia', '18999900101', '', '', '', '', '', '']);

        self::assertSame('Xana', $r['name']);
        self::assertStringNotContainsString('<', $r['name']);
    }
}
