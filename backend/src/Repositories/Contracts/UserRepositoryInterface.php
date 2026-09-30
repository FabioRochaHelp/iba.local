<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /** @return list<User> */
    public function all(): array;

    public function findByAthlete(int $athleteId): ?User;

    /** @return array<int, string> user_id => nome do atleta/responsável vinculado */
    public function linkNames(): array;

    public function findByGuardian(int $guardianId): ?User;

    /** @param array{name: string, email: string, password_hash: string, role: string, active?: bool, must_change_password?: bool, athlete_id?: ?int, guardian_id?: ?int} $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    public function updatePassword(int $id, string $hash, bool $mustChange): void;

    public function touchLastLogin(int $id): void;

    public function emailExists(string $email, ?int $exceptId = null): bool;
}
