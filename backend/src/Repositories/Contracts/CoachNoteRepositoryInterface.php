<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CoachNoteRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function forAthlete(int $athleteId, bool $onlyVisible = false): array;

    public function setVisibility(int $id, bool $visible): void;

    public function delete(int $id): void;
}
