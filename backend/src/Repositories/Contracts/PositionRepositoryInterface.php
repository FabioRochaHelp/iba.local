<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PositionRepositoryInterface
{
    /** @return list<array{id: int, name: string, short_name: string}> */
    public function all(): array;

    /**
     * @param list<int> $ids
     * @return list<int> apenas os IDs existentes
     */
    public function existingIds(array $ids): array;
}
