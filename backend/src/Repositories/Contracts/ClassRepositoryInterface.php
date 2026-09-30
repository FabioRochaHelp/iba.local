<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ClassRepositoryInterface
{
    /**
     * @param array{coach_id?: ?int, active?: ?bool, weekday?: ?int} $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    /** @return list<array<string, mixed>> atletas ativos da turma */
    public function roster(int $classId): array;

    /** @param list<int> $athleteIds */
    public function syncAthletes(int $classId, array $athleteIds): void;

    /** O professor é responsável por alguma turma ativa em que o atleta está? */
    public function coachHasAthlete(int $coachId, int $athleteId): bool;

    /** @return list<array<string, mixed>> turmas ativas do atleta (com horário e professor) */
    public function classesOfAthlete(int $athleteId): array;

    /** @return list<array<string, mixed>> atletas ativos fora da turma, nascidos no intervalo */
    public function suggestions(int $classId, ?int $minYear, ?int $maxYear): array;
}
