<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AttendanceRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findSession(int $id): ?array;

    public function findSessionId(int $classId, string $date): ?int;

    public function createSession(int $classId, string $date, ?int $coachId): int;

    public function updateSessionNotes(int $sessionId, ?string $notes): void;

    /** @return array<int, array{status: string, note: ?string}> athlete_id => registro */
    public function records(int $sessionId): array;

    /** @param list<array{athlete_id: int, status: string, note: ?string}> $records */
    public function saveRecords(int $sessionId, array $records): void;

    /** @return list<array<string, mixed>> chamadas da turma com contagens */
    public function sessionsForClass(int $classId, string $from, string $to): array;

    /** @return list<array<string, mixed>> por atleta: presentes, faltas, justificadas, total */
    public function classReport(int $classId, string $from, string $to): array;

    /** @return array{present: int, absent: int, justified: int, total: int, recent: list<array<string, mixed>>} */
    public function athleteStats(int $athleteId, string $from, string $to): array;

    /** @return array{present: int, total: int} */
    public function overall(string $from, string $to, ?int $coachId = null): array;
}
