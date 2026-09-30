<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;

final class CsvReader implements SpreadsheetReaderInterface
{
    public function supports(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt', 'tsv'], true);
    }

    public function read(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Não foi possível ler {$path}");
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // Excel em PT-BR
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = null;
        $rows = [];
        while (($cols = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($cols === [null] || implode('', $cols) === '') {
                continue;
            }
            $cols = array_map(static fn ($c) => trim((string) $c), $cols);
            if ($header === null) {
                $header = $cols;
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = $cols[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
