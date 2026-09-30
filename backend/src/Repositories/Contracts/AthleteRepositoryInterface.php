<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AthleteRepositoryInterface
{
    /**
     * @param array{search?: ?string, status?: ?string, plan_id?: ?int, birth_year?: ?int, guardian_id?: ?int,
     *              health?: ?bool, sort?: ?string, order?: ?string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** @return array<string, mixed>|null com guardian, phones, positions e plano vigente */
    public function find(int $id): ?array;

    public function exists(int $id): bool;

    /** Responsável do atleta (null se o atleta não existe ou foi excluído). */
    public function guardianIdOf(int $athleteId): ?int;

    /** @return list<array{id: int, name: string, birth_date: ?string, status: string}> filhos ativos do responsável */
    public function byGuardian(int $guardianId): array;

    public function findIdByNameAndBirth(string $name, ?string $birthDate): ?int;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    public function softDelete(int $id): void;

    /** @param list<int> $positionIds */
    public function syncPositions(int $id, array $positionIds): void;

    /** @return array{active: int, inactive: int, total: int} */
    public function counts(): array;

    /** @return list<array<string, mixed>> */
    public function birthdaysInMonth(int $month): array;
}
