<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\MeasurementRepositoryInterface;

final class PdoMeasurementRepository implements MeasurementRepositoryInterface
{
    private const COLUMNS = ['athlete_id', 'measured_at', 'height_cm', 'weight_kg', 'sprint_20m_s', 'vertical_jump_cm', 'endurance_m', 'notes', 'recorded_by'];

    public function __construct(private Connection $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert('physical_measurements', array_intersect_key($data, array_flip(self::COLUMNS)));
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, athlete_id, recorded_by FROM physical_measurements WHERE id = ?', [$id]);
    }

    public function forAthlete(int $athleteId): array
    {
        return array_map(static function (array $r): array {
            $h = $r['height_cm'] !== null ? (float) $r['height_cm'] : null;
            $w = $r['weight_kg'] !== null ? (float) $r['weight_kg'] : null;

            return [
                'id' => (int) $r['id'],
                'measured_at' => $r['measured_at'],
                'height_cm' => $h,
                'weight_kg' => $w,
                'bmi' => $h && $w ? round($w / (($h / 100) ** 2), 1) : null,
                'sprint_20m_s' => $r['sprint_20m_s'] !== null ? (float) $r['sprint_20m_s'] : null,
                'vertical_jump_cm' => $r['vertical_jump_cm'] !== null ? (float) $r['vertical_jump_cm'] : null,
                'endurance_m' => $r['endurance_m'] !== null ? (int) $r['endurance_m'] : null,
                'notes' => $r['notes'],
                'recorded_by' => $r['recorded_by'] !== null ? (int) $r['recorded_by'] : null,
                'recorded_by_name' => $r['recorded_by_name'],
            ];
        }, $this->db->fetchAll(
            'SELECT m.*, u.name AS recorded_by_name FROM physical_measurements m LEFT JOIN users u ON u.id = m.recorded_by
              WHERE m.athlete_id = ? ORDER BY m.measured_at DESC, m.id DESC',
            [$athleteId],
        ));
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM physical_measurements WHERE id = ?', [$id]);
    }
}
