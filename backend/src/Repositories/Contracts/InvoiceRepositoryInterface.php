<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface InvoiceRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findByAthleteAndMonth(int $athleteId, string $referenceMonth): ?array;

    /** @return array<string, mixed>|null mensalidade com atleta, responsável e plano */
    public function find(int $id): ?array;

    /**
     * Bloqueia a linha até o fim da transação (evita baixa duplicada concorrente).
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array;

    /**
     * @param array{month?: ?string, status?: ?string, search?: ?string, athlete_id?: ?int, sort?: ?string, order?: ?string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** @return list<array<string, mixed>> */
    public function forAthlete(int $athleteId): array;

    /** @return list<int> atletas que já têm mensalidade no mês */
    public function billedAthleteIds(string $referenceMonth): array;

    /** @return array<string, mixed> totais do mês (regime de competência) */
    public function monthSummary(string $referenceMonth): array;

    /** @return list<array{month: string, expected: string, received: string}> */
    public function monthlySeries(string $fromMonth, string $toMonth): array;

    /** @return list<array<string, mixed>> mensalidades vencidas com dados do responsável */
    public function overdue(string $today): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;
}
