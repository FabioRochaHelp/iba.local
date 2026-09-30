<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AuditLogRepositoryInterface
{
    /** @param array<string, mixed> $entry */
    public function insert(array $entry): void;

    /**
     * @param array{entity?: string, user_id?: int, action?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;
}
