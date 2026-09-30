<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\ClassRepositoryInterface;

final class PdoClassRepository implements ClassRepositoryInterface
{
    private const COLUMNS = ['name', 'weekday', 'start_time', 'end_time', 'category', 'min_birth_year', 'max_birth_year', 'coach_id', 'active'];

    private const SELECT = "SELECT c.id, c.name, c.weekday, TIME_FORMAT(c.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(c.end_time, '%H:%i') AS end_time, c.category, c.min_birth_year, c.max_birth_year, c.coach_id, c.active,
            u.name AS coach_name,
            (SELECT COUNT(*) FROM class_athlete ca JOIN athletes a ON a.id = ca.athlete_id
              WHERE ca.class_id = c.id AND a.deleted_at IS NULL AND a.status = 'ativo') AS athletes_count,
            (SELECT MAX(s.session_date) FROM attendance_sessions s WHERE s.class_id = c.id) AS last_session_date
       FROM classes c LEFT JOIN users u ON u.id = c.coach_id";

    public function __construct(private Connection $db)
    {
    }

    public function all(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['coach_id'])) {
            $where[] = 'c.coach_id = :coach';
            $params['coach'] = (int) $filters['coach_id'];
        }
        if (isset($filters['active']) && $filters['active'] !== null) {
            $where[] = 'c.active = :active';
            $params['active'] = (int) $filters['active'];
        }
        if (!empty($filters['weekday'])) {
            $where[] = 'c.weekday = :weekday';
            $params['weekday'] = (int) $filters['weekday'];
        }

        return array_map([$this, 'map'], $this->db->fetchAll(
            self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.active DESC, c.weekday, c.start_time, c.name',
            $params,
        ));
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE c.id = ?', [$id]);

        return $row ? $this->map($row) : null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('classes', $this->filter($data));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('classes', $this->filter($data), ['id' => $id]);
    }

    public function roster(int $classId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'birth_date' => $r['birth_date'],
            'has_health_condition' => (bool) $r['has_health_condition'],
            'health_condition' => $r['health_condition'],
            'joined_at' => $r['joined_at'],
            'plan_days' => $r['days_per_week'] !== null ? (int) $r['days_per_week'] : null,
        ], $this->db->fetchAll(
            "SELECT a.id, a.name, a.birth_date, a.has_health_condition, a.health_condition, ca.joined_at, p.days_per_week
               FROM class_athlete ca
               JOIN athletes a ON a.id = ca.athlete_id
          LEFT JOIN athlete_plans ap ON ap.athlete_id = a.id AND ap.end_date IS NULL
          LEFT JOIN plans p ON p.id = ap.plan_id
              WHERE ca.class_id = ? AND a.deleted_at IS NULL AND a.status = 'ativo'
              ORDER BY a.name",
            [$classId],
        ));
    }

    public function syncAthletes(int $classId, array $athleteIds): void
    {
        $current = array_map('intval', array_column(
            $this->db->fetchAll('SELECT athlete_id FROM class_athlete WHERE class_id = ?', [$classId]),
            'athlete_id',
        ));
        $remove = array_diff($current, $athleteIds);
        $add = array_diff($athleteIds, $current);

        foreach ($remove as $id) {
            $this->db->execute('DELETE FROM class_athlete WHERE class_id = ? AND athlete_id = ?', [$classId, $id]);
        }
        foreach ($add as $id) {
            $this->db->insert('class_athlete', ['class_id' => $classId, 'athlete_id' => $id, 'joined_at' => date('Y-m-d')]);
        }
    }

    public function coachHasAthlete(int $coachId, int $athleteId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM class_athlete ca JOIN classes c ON c.id = ca.class_id
              WHERE c.coach_id = ? AND ca.athlete_id = ? AND c.active = 1 LIMIT 1',
            [$coachId, $athleteId],
        ) !== null;
    }

    public function classesOfAthlete(int $athleteId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'weekday' => (int) $r['weekday'],
            'start_time' => $r['start_time'],
            'end_time' => $r['end_time'],
            'category' => $r['category'],
            'coach_name' => $r['coach_name'],
        ], $this->db->fetchAll(
            "SELECT c.id, c.name, c.weekday, TIME_FORMAT(c.start_time, '%H:%i') AS start_time,
                    TIME_FORMAT(c.end_time, '%H:%i') AS end_time, c.category, u.name AS coach_name
               FROM class_athlete ca JOIN classes c ON c.id = ca.class_id LEFT JOIN users u ON u.id = c.coach_id
              WHERE ca.athlete_id = ? AND c.active = 1 ORDER BY c.weekday, c.start_time",
            [$athleteId],
        ));
    }

    public function suggestions(int $classId, ?int $minYear, ?int $maxYear): array
    {
        $where = ["a.deleted_at IS NULL", "a.status = 'ativo'", 'NOT EXISTS (SELECT 1 FROM class_athlete ca WHERE ca.class_id = :cid AND ca.athlete_id = a.id)'];
        $params = ['cid' => $classId];
        if ($minYear !== null) {
            $where[] = 'YEAR(a.birth_date) >= :miny';
            $params['miny'] = $minYear;
        }
        if ($maxYear !== null) {
            $where[] = 'YEAR(a.birth_date) <= :maxy';
            $params['maxy'] = $maxYear;
        }

        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'birth_date' => $r['birth_date'],
            'classes_count' => (int) $r['classes_count'],
        ], $this->db->fetchAll(
            'SELECT a.id, a.name, a.birth_date,
                    (SELECT COUNT(*) FROM class_athlete x WHERE x.athlete_id = a.id) AS classes_count
               FROM athletes a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.birth_date DESC, a.name',
            $params,
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        if (array_key_exists('active', $data)) {
            $data['active'] = (int) $data['active'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'weekday' => (int) $r['weekday'],
            'start_time' => $r['start_time'],
            'end_time' => $r['end_time'],
            'category' => $r['category'],
            'min_birth_year' => $r['min_birth_year'] !== null ? (int) $r['min_birth_year'] : null,
            'max_birth_year' => $r['max_birth_year'] !== null ? (int) $r['max_birth_year'] : null,
            'coach_id' => $r['coach_id'] !== null ? (int) $r['coach_id'] : null,
            'coach_name' => $r['coach_name'],
            'active' => (bool) $r['active'],
            'athletes_count' => (int) $r['athletes_count'],
            'last_session_date' => $r['last_session_date'],
        ];
    }
}
