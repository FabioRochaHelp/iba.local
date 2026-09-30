<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface MeasurementRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> mais recente primeiro */
    public function forAthlete(int $athleteId): array;

    public function delete(int $id): void;
}
