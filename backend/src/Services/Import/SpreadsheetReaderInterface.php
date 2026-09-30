<?php

declare(strict_types=1);

namespace App\Services\Import;

interface SpreadsheetReaderInterface
{
    public function supports(string $path): bool;

    /**
     * Lê a primeira planilha: primeira linha = cabeçalho.
     *
     * @return list<array<string, string>> linhas indexadas pelo cabeçalho
     */
    public function read(string $path): array;
}
