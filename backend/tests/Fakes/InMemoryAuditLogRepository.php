<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Repositories\Contracts\AuditLogRepositoryInterface;

final class InMemoryAuditLogRepository implements AuditLogRepositoryInterface
{
    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function insert(array $entry): void
    {
        $this->entries[] = $entry;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return ['items' => $this->entries, 'total' => count($this->entries)];
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_column($this->entries, 'action');
    }
}
