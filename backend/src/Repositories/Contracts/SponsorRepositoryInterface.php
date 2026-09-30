<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SponsorRepositoryInterface
{
    /** @return list<array<string, mixed>> com total aportado */
    public function all(?string $search = null): array;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    public function delete(int $id): void;

    public function sponsorshipsCount(int $id): int;
}
