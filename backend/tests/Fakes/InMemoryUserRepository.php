<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        return isset($this->rows[$id]) ? User::fromRow($this->rows[$id]) : null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->rows as $row) {
            if ($row['email'] === mb_strtolower($email)) {
                return User::fromRow($row);
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values(array_map([User::class, 'fromRow'], $this->rows));
    }

    public function create(array $data): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'active' => $data['active'] ?? true,
            'must_change_password' => $data['must_change_password'] ?? true,
            'last_login_at' => null,
            'athlete_id' => $data['athlete_id'] ?? null,
            'guardian_id' => $data['guardian_id'] ?? null,
        ];

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->rows[$id] = array_merge($this->rows[$id], array_intersect_key($data, array_flip(['name', 'email', 'role', 'active'])));
    }

    public function updatePassword(int $id, string $hash, bool $mustChange): void
    {
        $this->rows[$id]['password_hash'] = $hash;
        $this->rows[$id]['must_change_password'] = $mustChange;
    }

    public function touchLastLogin(int $id): void
    {
        $this->rows[$id]['last_login_at'] = date('Y-m-d H:i:s');
    }

    public function findByAthlete(int $athleteId): ?User
    {
        foreach ($this->rows as $row) {
            if (($row['athlete_id'] ?? null) === $athleteId) {
                return User::fromRow($row);
            }
        }

        return null;
    }

    public function linkNames(): array
    {
        return [];
    }

    public function findByGuardian(int $guardianId): ?User
    {
        foreach ($this->rows as $row) {
            if (($row['guardian_id'] ?? null) === $guardianId) {
                return User::fromRow($row);
            }
        }

        return null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $user = $this->findByEmail($email);

        return $user !== null && $user->id !== $exceptId;
    }
}
