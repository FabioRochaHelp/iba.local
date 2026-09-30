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
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Não foi possível abrir {$path}");
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = $this->xml($xml);
            foreach ($sst->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                }
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $dateStyles = $this->dateStyleIndexes($zip->getFromName('xl/styles.xml') ?: '');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Planilha vazia ou formato não suportado.');
        }

        $grid = [];
        foreach ($this->xml($sheetXml)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = $this->columnIndex((string) preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];
                $value = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : '');
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === '' && $value !== '' && is_numeric($value) && in_array((int) $c['s'], $dateStyles, true)) {
                    $value = gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
                }
                $cells[$col] = trim($value);
            }
            if ($cells !== [] && implode('', $cells) !== '') {
                $grid[] = $cells;
            }
        }

        if ($grid === []) {
            return [];
        }
        $header = array_shift($grid);
        $rows = [];
        foreach ($grid as $cells) {
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
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
