<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Leitor mínimo de .xlsx (ZipArchive + SimpleXML), sem dependências.
 * Lê a primeira planilha; datas numéricas do Excel viram AAAA-MM-DD.
 */
final class XlsxReader implements SpreadsheetReaderInterface
{
    public function supports(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx' && class_exists(ZipArchive::class);
    }

    public function read(string $path): array
    {
        return $this->readSheet($path, null, 1);
    }

    /** @return list<string> nomes das abas, na ordem do arquivo */
    public function sheetNames(string $path): array
    {
        $zip = $this->open($path);
        $names = array_keys($this->sheetPaths($zip));
        $zip->close();

        return $names;
    }

    /**
     * Lê uma aba pelo nome (null = primeira) usando a linha $headerRow
     * (numeração do Excel, base 1) como cabeçalho. Colunas sem título são
     * ignoradas; linhas totalmente vazias também.
     *
     * @return list<array<string, string>>
     */
    public function readSheet(string $path, ?string $sheetName = null, int $headerRow = 1): array
    {
        $zip = $this->open($path);

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach ($this->xml($xml)->si as $si) {
                $text = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $sheets = $this->sheetPaths($zip);
        $target = $sheetName === null ? reset($sheets) : ($sheets[$sheetName] ?? null);
        if ($target === null || $target === false) {
            $zip->close();
            throw new RuntimeException("Aba não encontrada: {$sheetName}");
        }

        $dateStyles = $this->dateStyleIndexes($zip->getFromName('xl/styles.xml') ?: '');
        $sheetXml = $zip->getFromName($target);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Planilha vazia ou formato não suportado.');
        }

        $grid = []; // número da linha => [coluna => valor]
        foreach ($this->xml($sheetXml)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = $this->columnIndex((string) preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : '');
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === '' && $value !== '' && is_numeric($value) && in_array((int) $c['s'], $dateStyles, true)) {
                    $value = gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
                } elseif ($type === '' && is_numeric($value) && preg_match('/^\d+(\.0+)?$/', $value)) {
                    $value = (string) (int) $value; // 18999990000.0 -> 18999990000
                }
                $cells[$col] = trim($value);
            }
            if ($cells !== [] && implode('', $cells) !== '') {
                $grid[(int) $row['r']] = $cells;
            }
        }

        $header = $grid[$headerRow] ?? null;
        if ($header === null) {
            return [];
        }
        $rows = [];
        foreach ($grid as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                if ($name !== '') {
                    $row[$name] = $cells[$i] ?? '';
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Não foi possível abrir {$path}");
        }

        return $zip;
    }

    /** @return array<string, string> nome da aba => caminho do XML no pacote */
    private function sheetPaths(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return ['Planilha1' => 'xl/worksheets/sheet1.xml'];
        }

        $targets = [];
        foreach ($this->xml($rels)->Relationship as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }

        $paths = [];
        $wb = $this->xml($workbook);
        foreach ($wb->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($targets[$rid])) {
                $paths[(string) $sheet['name']] = $targets[$rid];
            }
        }

        return $paths;
    }

    private function xml(string $content): SimpleXMLElement
    {
        // LIBXML_NONET: sem acesso à rede; entidades externas não são carregadas (anti-XXE).
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('XML inválido na planilha.');
        }

        return $xml;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }

    /** @return list<int> */
    private function dateStyleIndexes(string $stylesXml): array
    {
        if ($stylesXml === '') {
            return [];
        }
        $styles = $this->xml($stylesXml);
        $custom = [];
        foreach ($styles->numFmts->numFmt ?? [] as $fmt) {
            if (preg_match('/[dmy]/i', (string) $fmt['formatCode']) && !str_contains((string) $fmt['formatCode'], '"')) {
                $custom[] = (int) $fmt['numFmtId'];
            }
        }
        $builtinDates = [14, 15, 16, 17, 22];
        $indexes = [];
        $i = 0;
        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            if (in_array($id, $builtinDates, true) || in_array($id, $custom, true)) {
                $indexes[] = $i;
            }
            $i++;
        }

        return $indexes;
    }
}
