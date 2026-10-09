<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\XlsxReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** XLSX sintético (dados fictícios) com duas abas e cabeçalho fora da linha 1. */
final class XlsxReaderSheetTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($this->file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Pagamentos" sheetId="1" r:id="rId1"/><sheet name="Alunos" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="/xl/worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Título</t></si><si><t>NOME</t></si><si><t>abr/26</t></si><si><t>Ana Fictícia</t></si><si><t>Nome do atleta:</t></si><si><t>Telefone</t></si><si><r><t>Bia </t></r><r><t>Exemplo</t></r></si></sst>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs></styleSheet>');
        // Aba 1: título na linha 1, cabeçalho na linha 3, coluna A sem título
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="B1" t="s"><v>0</v></c></row>'
            . '<row r="3"><c r="B3" t="s"><v>1</v></c><c r="C3" t="s"><v>2</v></c><c r="D3" t="s"><v>5</v></c></row>'
            . '<row r="4"><c r="A4"><v>1</v></c><c r="B4" t="s"><v>3</v></c><c r="C4"><v>110</v></c><c r="D4"><v>18999990001</v></c></row>'
            . '<row r="5"/>'
            . '</sheetData></worksheet>');
        // Aba 2: cabeçalho na linha 1, data com estilo de data e texto rico
        $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>4</v></c><c r="B1" t="inlineStr"><is><t>Nascimento</t></is></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>6</v></c><c r="B2" s="1"><v>42131</v></c></row>'
            . '</sheetData></worksheet>');
        $zip->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testListsSheetsAndReadsByNameWithHeaderRow(): void
    {
        $r = new XlsxReader();

        self::assertSame(['Pagamentos', 'Alunos'], $r->sheetNames($this->file));

        $pay = $r->readSheet($this->file, 'Pagamentos', 3);
        self::assertSame([['NOME' => 'Ana Fictícia', 'abr/26' => '110', 'Telefone' => '18999990001']], $pay);

        $alunos = $r->readSheet($this->file, 'Alunos');
        self::assertSame([['Nome do atleta:' => 'Bia Exemplo', 'Nascimento' => '2015-05-07']], $alunos);

        // read() continua lendo a primeira aba com cabeçalho na linha 1 (compatibilidade)
        self::assertSame('Título', array_key_first($r->read($this->file)[0] ?? ['Título' => '']));
    }

    public function testUnknownSheetFails(): void
    {
        $this->expectException(\RuntimeException::class);
        (new XlsxReader())->readSheet($this->file, 'Não existe');
    }
}
