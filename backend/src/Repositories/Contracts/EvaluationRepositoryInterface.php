<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface EvaluationRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function criteria(bool $onlyActive = false): array;

    /** @param array<string, mixed> $data */
    public function saveCriterion(?int $id, array $data): int;

    public function criterionNameExists(string $name, ?int $exceptId = null): bool;

    /**
     * @param array{athlete_id: int, evaluated_by: int, evaluation_date: string, general_comment: ?string, visible_to_athlete: bool} $data
     * @param array<int, int> $scores criterion_id => nota
     */
    public function create(array $data, array $scores): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> avaliações com notas, mais recente primeiro */
    public function forAthlete(int $athleteId, bool $onlyVisible = false): array;

    public function delete(int $id): void;
}
