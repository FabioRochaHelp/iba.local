<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly Role $role,
        public readonly bool $active,
        public readonly bool $mustChangePassword,
        public readonly string $passwordHash = '',
        public readonly ?string $lastLoginAt = null,
        public readonly ?int $athleteId = null,
        public readonly ?int $guardianId = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            Role::from((string) $row['role']),
            (bool) $row['active'],
            (bool) $row['must_change_password'],
            (string) ($row['password_hash'] ?? ''),
            isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            isset($row['athlete_id']) ? (int) $row['athlete_id'] : null,
            isset($row['guardian_id']) ? (int) $row['guardian_id'] : null,
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Representação pública — nunca inclui o hash da senha.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'active' => $this->active,
            'must_change_password' => $this->mustChangePassword,
            'last_login_at' => $this->lastLoginAt,
            'athlete_id' => $this->athleteId,
            'guardian_id' => $this->guardianId,
        ];
    }
}
