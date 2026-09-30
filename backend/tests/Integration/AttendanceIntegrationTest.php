<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\ValidationException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Pdo\PdoAthleteRepository;
use App\Repositories\Pdo\PdoAttendanceRepository;
use App\Repositories\Pdo\PdoAuditLogRepository;
use App\Repositories\Pdo\PdoClassRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Classes\AttendanceService;
use App\Services\Classes\ClassAccessPolicy;
use App\Services\Classes\ClassService;
use RuntimeException;
use Tests\Fakes\RequestFactory;

final class AttendanceIntegrationTest extends DatabaseTestCase
{
    public function testClassAndAttendanceFlow(): void
    {
        $db = self::$db;
        $adminId = (int) $db->fetchValue("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        $athletes = array_map('intval', array_column($db->fetchAll("SELECT id FROM athletes WHERE deleted_at IS NULL AND status = 'ativo' ORDER BY id LIMIT 4"), 'id'));
        if ($adminId === 0 || count($athletes) < 4) {
            self::markTestSkipped('Dados insuficientes.');
        }
        $req = RequestFactory::make('POST', '/api/x');
        $admin = new User($adminId, 'Admin', 'a@x', Role::Admin, true, false);
        $audit = new AuditLogger(new PdoAuditLogRepository($db));
        $classRepo = new PdoClassRepository($db);
        $policy = new ClassAccessPolicy();
        $classes = new ClassService($classRepo, new PdoAthleteRepository($db), new PdoUserRepository($db), $policy, $db, $audit);
        $attendance = new AttendanceService($classRepo, new PdoAttendanceRepository($db), $policy, $db, $audit);

        try {
            $db->transaction(function () use ($db, $classes, $attendance, $admin, $req, $athletes): void {
                $profId = $db->insert('users', ['name' => 'Prof IT', 'email' => 'it-prof@teste.local', 'password_hash' => 'x', 'role' => 'professor', 'must_change_password' => 0]);
                $otherId = $db->insert('users', ['name' => 'Outro IT', 'email' => 'it-outro@teste.local', 'password_hash' => 'x', 'role' => 'professor', 'must_change_password' => 0]);
                $prof = new User($profId, 'Prof IT', 'it-prof@teste.local', Role::Professor, true, false);
                $other = new User($otherId, 'Outro IT', 'it-outro@teste.local', Role::Professor, true, false);

                $classId = $classes->save(null, ['name' => 'IT Sub-12 Seg', 'weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'coach_id' => $profId], $req);
                $classes->setAthletes($classId, array_slice($athletes, 0, 3), $req);

                self::assertCount(1, array_filter($classes->list($prof), static fn ($c) => $c['id'] === $classId));
                self::assertCount(0, array_filter($classes->list($other), static fn ($c) => $c['id'] === $classId), 'professor não vê turma alheia');

                try {
                    $attendance->open($classId, date('Y-m-d'), $other);
                    self::fail('IDOR: outro professor não pode abrir a chamada');
                } catch (ForbiddenException) {
                }
                try {
                    $attendance->open($classId, date('Y-m-d', strtotime('+1 day')), $prof);
                    self::fail('Data futura deveria falhar');
                } catch (ValidationException) {
                }

                $session = $attendance->open($classId, date('Y-m-d'), $prof);
                self::assertCount(3, $session['roster']);
                self::assertSame($session['id'], $attendance->open($classId, date('Y-m-d'), $prof)['id'], 'reabre a mesma sessão');

                try {
                    $attendance->save($session['id'], [['athlete_id' => $athletes[3], 'status' => 'presente']], null, $prof, $req);
                    self::fail('Atleta fora da turma deveria falhar');
                } catch (ValidationException) {
                }

                $records = [
                    ['athlete_id' => $athletes[0], 'status' => 'presente'],
                    ['athlete_id' => $athletes[1], 'status' => 'falta'],
                    ['athlete_id' => $athletes[2], 'status' => 'justificada', 'note' => 'atestado'],
                ];
                $saved = $attendance->save($session['id'], $records, 'Treino tático', $prof, $req);
                self::assertSame(3, $saved['recorded']);
                // regravar não duplica
                $records[1]['status'] = 'presente';
                self::assertSame(3, $attendance->save($session['id'], $records, null, $admin, $req)['recorded']);

                $report = $attendance->classReport($classId, date('Y-m-d'), date('Y-m-d'), $prof);
                self::assertSame(1, count($report['sessions']));
                self::assertSame(2, $report['sessions'][0]['present']);
                $byId = array_column($report['athletes'], null, 'athlete_id');
                self::assertSame(100, $byId[$athletes[0]]['rate']);
                self::assertSame(0, $byId[$athletes[2]]['rate'], 'justificada não conta como presença');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertNull($db->fetchValue("SELECT id FROM classes WHERE name = 'IT Sub-12 Seg'"));
    }
}
