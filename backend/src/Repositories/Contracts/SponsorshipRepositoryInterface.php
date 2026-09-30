<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SponsorshipRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    public function delete(int $id): void;

    /**
     * @param array{from?: ?string, to?: ?string, sponsor_id?: ?int, athlete_id?: ?int, search?: ?string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, sum: string}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** @return list<array{month: string, total: string}> */
    public function monthlyTotals(string $from, string $to): array;

    /** @return list<array{sponsor: string, total: string}> */
    public function totalsBySponsor(string $from, string $to): array;

    public function totalBetween(string $from, string $to): string;
}
