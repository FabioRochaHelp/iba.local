<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PlanRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function all(bool $onlyActive = false): array;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    public function nameExists(string $name, ?int $exceptId = null): bool;

    public function activeAthletesCount(int $id): int;
}
