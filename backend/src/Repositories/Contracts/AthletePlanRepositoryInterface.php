<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AthletePlanRepositoryInterface
{
    /** @return array<string, mixed>|null plano vigente (end_date nulo) */
    public function current(int $athleteId): ?array;

    /** @return array<string, mixed>|null plano vigente na data */
    public function activeOn(int $athleteId, string $date): ?array;

    /** @return list<array<string, mixed>> */
    public function history(int $athleteId): array;

    public function closeCurrent(int $athleteId, string $endDate): void;

    /** @param array{athlete_id: int, plan_id: int, start_date: string, discount_type: string, discount_value: string} $data */
    public function create(array $data): int;

    /**
     * Atletas ativos com plano vigente em algum dia do mês (o mais recente),
     * matriculados até o fim do mês.
     *
     * @return list<array{athlete_id: int, athlete_plan_id: int, monthly_fee: string, discount_type: string, discount_value: string}>
     */
    public function billable(string $monthStart, string $monthEnd): array;

    public function deleteCurrentIfStartedOn(int $athleteId, string $date): void;
}
