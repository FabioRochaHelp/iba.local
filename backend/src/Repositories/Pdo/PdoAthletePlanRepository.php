<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AthletePlanRepositoryInterface;

final class PdoAthletePlanRepository implements AthletePlanRepositoryInterface
{
    private const SELECT = 'SELECT ap.id, ap.athlete_id, ap.plan_id, ap.start_date, ap.end_date, ap.discount_type, ap.discount_value,
                                   p.name AS plan_name, p.days_per_week, p.monthly_fee
                              FROM athlete_plans ap JOIN plans p ON p.id = ap.plan_id';

    public function __construct(private Connection $db)
    {
    }

    public function current(int $athleteId): ?array
    {
        return $this->db->fetchOne(self::SELECT . ' WHERE ap.athlete_id = ? AND ap.end_date IS NULL ORDER BY ap.id DESC LIMIT 1', [$athleteId]);
    }

    public function activeOn(int $athleteId, string $date): ?array
    {
        return $this->db->fetchOne(
            self::SELECT . ' WHERE ap.athlete_id = ? AND ap.start_date <= ? AND (ap.end_date IS NULL OR ap.end_date >= ?)
                             ORDER BY ap.start_date DESC, ap.id DESC LIMIT 1',
            [$athleteId, $date, $date],
        );
    }

    public function history(int $athleteId): array
    {
        return $this->db->fetchAll(self::SELECT . ' WHERE ap.athlete_id = ? ORDER BY ap.start_date DESC, ap.id DESC', [$athleteId]);
    }

    public function closeCurrent(int $athleteId, string $endDate): void
    {
        $this->db->execute(
            'UPDATE athlete_plans SET end_date = GREATEST(start_date, ?) WHERE athlete_id = ? AND end_date IS NULL',
            [$endDate, $athleteId],
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('athlete_plans', [
            'athlete_id' => $data['athlete_id'],
            'plan_id' => $data['plan_id'],
            'start_date' => $data['start_date'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
        ]);
    }

    public function billable(string $monthStart, string $monthEnd): array
    {
        $rows = $this->db->fetchAll(
            "SELECT ap.athlete_id, ap.id AS athlete_plan_id, p.monthly_fee, ap.discount_type, ap.discount_value
               FROM athlete_plans ap
               JOIN plans p ON p.id = ap.plan_id
               JOIN athletes a ON a.id = ap.athlete_id
              WHERE a.deleted_at IS NULL AND a.status = 'ativo' AND a.enrollment_date <= :end1
                AND ap.start_date <= :end2 AND (ap.end_date IS NULL OR ap.end_date >= :start1)
                AND ap.id = (SELECT ap2.id FROM athlete_plans ap2
                              WHERE ap2.athlete_id = ap.athlete_id AND ap2.start_date <= :end3
                                AND (ap2.end_date IS NULL OR ap2.end_date >= :start2)
                              ORDER BY ap2.start_date DESC, ap2.id DESC LIMIT 1)
              ORDER BY ap.athlete_id",
            ['end1' => $monthEnd, 'end2' => $monthEnd, 'end3' => $monthEnd, 'start1' => $monthStart, 'start2' => $monthStart],
        );

        return array_map(static fn (array $r) => [
            'athlete_id' => (int) $r['athlete_id'],
            'athlete_plan_id' => (int) $r['athlete_plan_id'],
            'monthly_fee' => (string) $r['monthly_fee'],
            'discount_type' => (string) $r['discount_type'],
            'discount_value' => (string) $r['discount_value'],
        ], $rows);
    }

    public function deleteCurrentIfStartedOn(int $athleteId, string $date): void
    {
        $this->db->execute(
            'DELETE FROM athlete_plans WHERE athlete_id = ? AND end_date IS NULL AND start_date = ?
               AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.athlete_plan_id = athlete_plans.id)',
            [$athleteId, $date],
        );
    }
}
