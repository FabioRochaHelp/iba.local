<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface GoalRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function forAthlete(int $athleteId): array;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    public function delete(int $id): void;
}
