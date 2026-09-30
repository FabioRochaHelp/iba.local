<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AttendanceRepositoryInterface;

final class PdoAttendanceRepository implements AttendanceRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function findSession(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT s.id, s.class_id, s.session_date, s.coach_id, s.notes, s.created_at, u.name AS coach_name
               FROM attendance_sessions s LEFT JOIN users u ON u.id = s.coach_id WHERE s.id = ?',
            [$id],
        );
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['class_id'] = (int) $row['class_id'];

        return $row;
    }

    public function findSessionId(int $classId, string $date): ?int
    {
        $id = $this->db->fetchValue('SELECT id FROM attendance_sessions WHERE class_id = ? AND session_date = ?', [$classId, $date]);

        return $id === null ? null : (int) $id;
    }

    public function createSession(int $classId, string $date, ?int $coachId): int
    {
        return $this->db->insert('attendance_sessions', ['class_id' => $classId, 'session_date' => $date, 'coach_id' => $coachId]);
    }

    public function updateSessionNotes(int $sessionId, ?string $notes): void
    {
        $this->db->update('attendance_sessions', ['notes' => $notes], ['id' => $sessionId]);
    }

    public function records(int $sessionId): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT athlete_id, status, note FROM attendances WHERE session_id = ?', [$sessionId]) as $r) {
            $result[(int) $r['athlete_id']] = ['status' => $r['status'], 'note' => $r['note']];
        }

        return $result;
    }

    public function saveRecords(int $sessionId, array $records): void
    {
        foreach ($records as $rec) {
            $this->db->execute(
                'INSERT INTO attendances (session_id, athlete_id, status, note) VALUES (:s, :a, :st, :n)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)',
                ['s' => $sessionId, 'a' => $rec['athlete_id'], 'st' => $rec['status'], 'n' => $rec['note']],
            );
        }
    }

    public function sessionsForClass(int $classId, string $from, string $to): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'session_date' => $r['session_date'],
            'coach_name' => $r['coach_name'],
            'present' => (int) $r['present'],
            'absent' => (int) $r['absent'],
            'justified' => (int) $r['justified'],
            'total' => (int) $r['total'],
        ], $this->db->fetchAll(
            "SELECT s.id, s.session_date, u.name AS coach_name,
                    SUM(at.status = 'presente') AS present, SUM(at.status = 'falta') AS absent,
                    SUM(at.status = 'justificada') AS justified, COUNT(at.athlete_id) AS total
               FROM attendance_sessions s
          LEFT JOIN attendances at ON at.session_id = s.id
          LEFT JOIN users u ON u.id = s.coach_id
              WHERE s.class_id = ? AND s.session_date BETWEEN ? AND ?
           GROUP BY s.id ORDER BY s.session_date DESC",
            [$classId, $from, $to],
        ));
    }

    public function classReport(int $classId, string $from, string $to): array
    {
        return array_map(static fn (array $r) => [
            'athlete_id' => (int) $r['athlete_id'],
            'name' => $r['name'],
            'present' => (int) $r['present'],
            'absent' => (int) $r['absent'],
            'justified' => (int) $r['justified'],
            'total' => (int) $r['total'],
        ], $this->db->fetchAll(
            "SELECT a.id AS athlete_id, a.name,
                    SUM(at.status = 'presente') AS present, SUM(at.status = 'falta') AS absent,
                    SUM(at.status = 'justificada') AS justified, COUNT(at.session_id) AS total
               FROM attendances at
               JOIN attendance_sessions s ON s.id = at.session_id
               JOIN athletes a ON a.id = at.athlete_id
              WHERE s.class_id = ? AND s.session_date BETWEEN ? AND ?
           GROUP BY a.id ORDER BY a.name",
            [$classId, $from, $to],
        ));
    }

    public function athleteStats(int $athleteId, string $from, string $to): array
    {
        $agg = $this->db->fetchOne(
            "SELECT SUM(at.status = 'presente') AS present, SUM(at.status = 'falta') AS absent,
                    SUM(at.status = 'justificada') AS justified, COUNT(*) AS total
               FROM attendances at JOIN attendance_sessions s ON s.id = at.session_id
              WHERE at.athlete_id = ? AND s.session_date BETWEEN ? AND ?",
            [$athleteId, $from, $to],
        ) ?? [];
        $recent = $this->db->fetchAll(
            'SELECT s.session_date, c.name AS class_name, at.status, at.note
               FROM attendances at
               JOIN attendance_sessions s ON s.id = at.session_id
               JOIN classes c ON c.id = s.class_id
              WHERE at.athlete_id = ? AND s.session_date BETWEEN ? AND ?
              ORDER BY s.session_date DESC LIMIT 30',
            [$athleteId, $from, $to],
        );

        return [
            'present' => (int) ($agg['present'] ?? 0),
            'absent' => (int) ($agg['absent'] ?? 0),
            'justified' => (int) ($agg['justified'] ?? 0),
            'total' => (int) ($agg['total'] ?? 0),
            'recent' => $recent,
        ];
    }

    public function overall(string $from, string $to, ?int $coachId = null): array
    {
        $sql = "SELECT SUM(at.status = 'presente') AS present, COUNT(*) AS total
                  FROM attendances at JOIN attendance_sessions s ON s.id = at.session_id
                  JOIN classes c ON c.id = s.class_id
                 WHERE s.session_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($coachId !== null) {
            $sql .= ' AND c.coach_id = ?';
            $params[] = $coachId;
        }
        $row = $this->db->fetchOne($sql, $params) ?? [];

        return ['present' => (int) ($row['present'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }
}
