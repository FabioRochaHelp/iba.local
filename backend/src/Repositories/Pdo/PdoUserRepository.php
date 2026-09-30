<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

final class PdoUserRepository implements UserRepositoryInterface
{
    private const COLUMNS = 'id, name, email, password_hash, role, athlete_id, guardian_id, active, must_change_password, last_login_at';

    /** Colunas que podem ser alteradas por update() — whitelist. */
    private const UPDATABLE = ['name', 'email', 'role', 'active'];

    public function __construct(private Connection $db)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);

        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?', [mb_strtolower($email)]);

        return $row ? User::fromRow($row) : null;
    }

    public function all(): array
    {
        return array_map(
            [User::class, 'fromRow'],
            $this->db->fetchAll('SELECT ' . self::COLUMNS . " FROM users ORDER BY FIELD(role, 'admin', 'professor', 'responsavel', 'atleta'), name"),
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', [
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'active' => (int) ($data['active'] ?? true),
            'must_change_password' => (int) ($data['must_change_password'] ?? true),
            'athlete_id' => $data['athlete_id'] ?? null,
            'guardian_id' => $data['guardian_id'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::UPDATABLE));
        if (isset($data['active'])) {
            $data['active'] = (int) $data['active'];
        }
        $this->db->update('users', $data, ['id' => $id]);
    }

    public function updatePassword(int $id, string $hash, bool $mustChange): void
    {
        $this->db->update('users', [
            'password_hash' => $hash,
            'must_change_password' => (int) $mustChange,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public function findByAthlete(int $athleteId): ?User
    {
        $row = $this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE athlete_id = ?', [$athleteId]);

        return $row ? User::fromRow($row) : null;
    }

    public function linkNames(): array
    {
        $result = [];
        foreach ($this->db->fetchAll(
            'SELECT u.id, COALESCE(a.name, g.name) AS link_name FROM users u
               LEFT JOIN athletes a ON a.id = u.athlete_id
               LEFT JOIN guardians g ON g.id = u.guardian_id
              WHERE u.athlete_id IS NOT NULL OR u.guardian_id IS NOT NULL',
        ) as $r) {
            $result[(int) $r['id']] = (string) $r['link_name'];
        }

        return $result;
    }

    public function findByGuardian(int $guardianId): ?User
    {
        $row = $this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM users WHERE guardian_id = ?', [$guardianId]);

        return $row ? User::fromRow($row) : null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM users WHERE email = ? AND id <> ?',
            [mb_strtolower($email), $exceptId ?? 0],
        ) !== null;
    }
}
