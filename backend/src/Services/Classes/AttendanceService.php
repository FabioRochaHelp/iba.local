<?php

declare(strict_types=1);

namespace App\Services\Classes;

use App\Core\Database\Connection;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Services\Audit\AuditLogger;
use DateTimeImmutable;

/**
 * Chamada: abre (ou reabre) a sessão do dia e grava presenças em lote.
 * Frequência = presentes ÷ chamadas registradas (falta justificada não conta como presença).
 */
class AttendanceService
{
    public const STATUSES = ['presente', 'falta', 'justificada'];

    public function __construct(
        private ClassRepositoryInterface $classes,
        private AttendanceRepositoryInterface $attendance,
        private ClassAccessPolicy $policy,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> sessão + lista de chamada */
    public function open(int $classId, string $date, User $user): array
    {
        $class = $this->classes->find($classId) ?? throw new NotFoundException('Turma não encontrada.');
        $this->policy->authorize($user, $class);
        if ($date > date('Y-m-d')) {
            throw ValidationException::withField('date', 'Não é possível fazer chamada de data futura.');
        }
        if ($date < (new DateTimeImmutable('-60 days'))->format('Y-m-d')) {
            throw ValidationException::withField('date', 'Chamadas com mais de 60 dias não podem ser abertas.');
        }

        $sessionId = $this->attendance->findSessionId($classId, $date)
            ?? $this->attendance->createSession($classId, $date, $user->id);

        return $this->get($sessionId, $user);
    }

    /** @return array<string, mixed> */
    public function get(int $sessionId, User $user): array
    {
        $session = $this->attendance->findSession($sessionId) ?? throw new NotFoundException('Chamada não encontrada.');
        $class = $this->classes->find($session['class_id']) ?? throw new NotFoundException('Turma não encontrada.');
        $this->policy->authorize($user, $class);

        $records = $this->attendance->records($sessionId);
        $roster = array_map(static fn (array $a) => [
            'athlete_id' => $a['id'],
            'name' => $a['name'],
            'has_health_condition' => $a['has_health_condition'],
            'health_condition' => $a['health_condition'],
            'status' => $records[$a['id']]['status'] ?? null,
            'note' => $records[$a['id']]['note'] ?? null,
        ], $this->classes->roster($class['id']));

        return [
            'id' => $session['id'],
            'session_date' => $session['session_date'],
            'notes' => $session['notes'],
            'class' => [
                'id' => $class['id'],
                'name' => $class['name'],
                'weekday' => $class['weekday'],
                'start_time' => $class['start_time'],
                'end_time' => $class['end_time'],
                'category' => $class['category'],
            ],
            'roster' => $roster,
            'recorded' => count($records),
        ];
    }

    /**
     * @param list<array{athlete_id: int, status: string, note?: ?string}> $records
     * @return array<string, mixed>
     */
    public function save(int $sessionId, array $records, ?string $notes, User $user, Request $request): array
    {
        $session = $this->attendance->findSession($sessionId) ?? throw new NotFoundException('Chamada não encontrada.');
        $class = $this->classes->find($session['class_id']) ?? throw new NotFoundException('Turma não encontrada.');
        $this->policy->authorize($user, $class);

        // Só atletas da turma podem receber presença (impede gravar em atleta alheio).
        $allowed = array_flip(array_column($this->classes->roster($class['id']), 'id'));
        $clean = [];
        foreach ($records as $i => $rec) {
            if (!isset($allowed[$rec['athlete_id']])) {
                throw ValidationException::withField("records.{$i}.athlete_id", 'Atleta não pertence a esta turma.');
            }
            $clean[] = ['athlete_id' => (int) $rec['athlete_id'], 'status' => $rec['status'], 'note' => $rec['note'] ?? null];
        }

        $this->db->transaction(function () use ($sessionId, $clean, $notes): void {
            $this->attendance->saveRecords($sessionId, $clean);
            $this->attendance->updateSessionNotes($sessionId, $notes);
        });

        $counts = array_count_values(array_column($clean, 'status'));
        $this->audit->log($request, 'attendance_saved', 'attendance_session', $sessionId, [
            'class_id' => $class['id'],
            'date' => $session['session_date'],
            'present' => $counts['presente'] ?? 0,
            'absent' => $counts['falta'] ?? 0,
        ]);

        return $this->get($sessionId, $user);
    }

    /** @return array<string, mixed> */
    public function classReport(int $classId, string $from, string $to, User $user): array
    {
        $class = $this->classes->find($classId) ?? throw new NotFoundException('Turma não encontrada.');
        $this->policy->authorize($user, $class);

        $athletes = array_map(static fn (array $r) => $r + [
            'rate' => $r['total'] > 0 ? (int) round($r['present'] / $r['total'] * 100) : null,
        ], $this->attendance->classReport($classId, $from, $to));

        return [
            'from' => $from,
            'to' => $to,
            'sessions' => $this->attendance->sessionsForClass($classId, $from, $to),
            'athletes' => $athletes,
        ];
    }

    /** @return array<string, mixed> */
    public function athleteStats(int $athleteId, string $from, string $to): array
    {
        $stats = $this->attendance->athleteStats($athleteId, $from, $to);
        $stats['rate'] = $stats['total'] > 0 ? (int) round($stats['present'] / $stats['total'] * 100) : null;

        return $stats;
    }

    /** Frequência geral (presentes ÷ registros) nos últimos N dias. */
    public function overallRate(int $days, ?int $coachId = null): ?int
    {
        $r = $this->attendance->overall((new DateTimeImmutable("-{$days} days"))->format('Y-m-d'), date('Y-m-d'), $coachId);

        return $r['total'] > 0 ? (int) round($r['present'] / $r['total'] * 100) : null;
    }
}
