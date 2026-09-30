<?php

declare(strict_types=1);

namespace App\Repositories\Pdo\Concerns;

/**
 * Ordenação segura: o cliente escolhe uma CHAVE; a coluna SQL vem
 * de um mapa fixo no repositório (whitelist). Nada do usuário entra
 * cru no SQL.
 */
trait BuildsListing
{
    /**
     * @param array<string, string> $allowed chave pública => expressão SQL
     */
    protected function orderBy(?string $sort, ?string $direction, array $allowed, string $default): string
    {
        $column = $allowed[$sort ?? ''] ?? $allowed[$default];
        $dir = strtolower((string) $direction) === 'desc' ? 'DESC' : 'ASC';

        return "{$column} {$dir}";
    }

    /** Escapa curingas do LIKE para busca literal. */
    protected function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
