<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Pdo\PdoAthleteRepository;
use App\Repositories\Pdo\PdoAttendanceRepository;
use App\Repositories\Pdo\PdoAuditLogRepository;
use App\Repositories\Pdo\PdoClassRepository;
use App\Repositories\Pdo\PdoCoachNoteRepository;
use App\Repositories\Pdo\PdoEvaluationRepository;
use App\Repositories\Pdo\PdoGoalRepository;
use App\Repositories\Pdo\PdoGuardianRepository;
use App\Repositories\Pdo\PdoMeasurementRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Classes\AttendanceService;
use App\Services\Classes\ClassAccessPolicy;
use App\Services\Evolution\AthleteAccessPolicy;
use App\Services\Evolution\EvolutionService;
use App\Services\Portal\PortalService;
use App\Services\Security\NativePasswordHasher;
use App\Services\Users\PortalLinkValidator;
use App\Services\Users\UserService;
use App\Core\Session\SessionRevoker;
use RuntimeException;
use Tests\Fakes\RequestFactory;

/**
 * Permissões da evolução e do portal contra o banco real
 * (transação desfeita ao final).
 */
final class EvolutionPortalIntegrationTest extends DatabaseTestCase
{
    public function testPermissionsAndVisibility(): void
    {
        $db = self::$db;
        $adminId = (int) $db->fetchValue("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        // Um responsável com 2+ filhos ativos (irmãos) e um atleta de outra família.
        $gid = (int) $db->fetchValue("SELECT guardian_id FROM athletes WHERE deleted_at IS NULL AND status = 'ativo' GROUP BY guardian_id HAVING COUNT(*) >= 2 LIMIT 1");
        if ($adminId === 0 || $gid === 0) {
            self::markTestSkipped('Dados insuficientes.');
        }
        [$childA, $childB] = array_map('intval', array_column($db->fetchAll("SELECT id FROM athletes WHERE guardian_id = ? AND deleted_at IS NULL AND status = 'ativo' ORDER BY id LIMIT 2", [$gid]), 'id'));
        $other = (int) $db->fetchValue("SELECT id FROM athletes WHERE guardian_id <> ? AND deleted_at IS NULL AND status = 'ativo' LIMIT 1", [$gid]);

        $req = RequestFactory::make('POST', '/api/x');
        $audit = new AuditLogger(new PdoAuditLogRepository($db));
        $athletes = new PdoAthleteRepository($db);
        $classes = new PdoClassRepository($db);
        $users = new PdoUserRepository($db);
        $policy = new AthleteAccessPolicy($athletes, $classes);
        $evolution = new EvolutionService($policy, new PdoEvaluationRepository($db), new PdoMeasurementRepository($db),
            new PdoCoachNoteRepository($db), new PdoGoalRepository($db), $db, $audit);
        $attendance = new AttendanceService($classes, new PdoAttendanceRepository($db), new ClassAccessPolicy(), $db, $audit);
        $portal = new PortalService($policy, $classes, $attendance, $evolution);
        $userService = new UserService($users, new NativePasswordHasher(), new SessionRevoker($db), $audit,
            new PortalLinkValidator($athletes, new PdoGuardianRepository($db), $users));

        try {
            $db->transaction(function () use ($db, $adminId, $gid, $childA, $childB, $other, $req, $evolution, $portal, $policy, $userService): void {
                $admin = new User($adminId, 'Admin', 'a@x', Role::Admin, true, false);
                $coachId = $db->insert('users', ['name' => 'Coach IT', 'email' => 'it-coach@teste.local', 'password_hash' => 'x', 'role' => 'professor', 'must_change_password' => 0]);
                $strangerId = $db->insert('users', ['name' => 'Outro IT', 'email' => 'it-outro@teste.local', 'password_hash' => 'x', 'role' => 'professor', 'must_change_password' => 0]);
                $coach = new User($coachId, 'Coach IT', 'c@x', Role::Professor, true, false);
                $stranger = new User($strangerId, 'Outro IT', 'o@x', Role::Professor, true, false);
                $classId = $db->insert('classes', ['name' => 'IT Evo', 'weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'coach_id' => $coachId]);
                $db->insert('class_athlete', ['class_id' => $classId, 'athlete_id' => $childA, 'joined_at' => date('Y-m-d')]);

                // Contas do portal via UserService (valida vínculo e unicidade)
                $athleteAccount = $userService->create(['name' => 'Atleta IT', 'email' => 'it-atleta@teste.local', 'role' => 'atleta', 'athlete_id' => $childA], $req);
                $guardianAccount = $userService->create(['name' => 'Resp IT', 'email' => 'it-resp@teste.local', 'role' => 'responsavel', 'guardian_id' => $gid], $req);
                try {
                    $userService->create(['name' => 'Dup', 'email' => 'it-dup@teste.local', 'role' => 'atleta', 'athlete_id' => $childA], $req);
                    self::fail('Um atleta só pode ter uma conta');
                } catch (ValidationException) {
                }
                $athleteUser = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$athleteAccount['user']['id']]);
                $guardianUser = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$guardianAccount['user']['id']]);
                $athlete = User::fromRow($athleteUser);
                $guardian = User::fromRow($guardianUser);

                // Quem registra
                self::assertTrue($policy->canEdit($coach, $childA));
                self::assertFalse($policy->canEdit($coach, $childB), 'professor não avalia atleta fora das suas turmas');
                self::assertFalse($policy->canEdit($stranger, $childA));
                self::assertFalse($policy->canEdit($athlete, $childA), 'atleta nunca registra');
                try {
                    $evolution->addNote($childA, ['note_date' => date('Y-m-d'), 'type' => 'geral', 'content' => 'x'], $stranger, $req);
                    self::fail('Professor de outra turma não pode registrar');
                } catch (ForbiddenException) {
                }

                $criteria = array_column($evolution->criteria(true), 'id');
                $evolution->addEvaluation($childA, ['evaluation_date' => date('Y-m-d'), 'visible_to_athlete' => true,
                    'scores' => [['criterion_id' => $criteria[0], 'score' => 4], ['criterion_id' => $criteria[1], 'score' => 3]]], $coach, $req);
                $evolution->addEvaluation($childA, ['evaluation_date' => date('Y-m-d'), 'visible_to_athlete' => false,
                    'scores' => [['criterion_id' => $criteria[0], 'score' => 2]]], $admin, $req);
                $evolution->addNote($childA, ['note_date' => date('Y-m-d'), 'type' => 'ponto_forte', 'content' => 'Ótima visão de jogo', 'visible_to_athlete' => true], $coach, $req);
                $evolution->addNote($childA, ['note_date' => date('Y-m-d'), 'type' => 'comportamento', 'content' => 'Nota interna'], $coach, $req);
                $evolution->addMeasurement($childA, ['measured_at' => date('Y-m-d'), 'height_cm' => 150, 'weight_kg' => 42], $coach, $req);
                $goalId = $evolution->addGoal($childA, ['title' => 'Melhorar perna esquerda'], $coach, $req);

                // Visão da equipe vs. portal
                $staff = $evolution->summary($childA, $coach);
                self::assertCount(2, $staff['evaluations']);
                self::assertCount(2, $staff['notes']);
                self::assertSame(18.7, $staff['measurements'][0]['bmi']);

                $own = $portal->overview($childA, $athlete)['evolution'];
                self::assertCount(1, $own['evaluations'], 'avaliação oculta não aparece no portal');
                self::assertSame(3.5, $own['evaluations'][0]['average']);
                self::assertCount(1, $own['notes'], 'observação interna não aparece no portal');
                self::assertSame('Ótima visão de jogo', $own['notes'][0]['content']);
                self::assertArrayNotHasKey('can_edit', $own);

                // Atleta só vê a si mesmo; responsável vê os filhos
                self::assertSame([$childA], array_column($portal->athletes($athlete), 'id'));
                try {
                    $portal->overview($childB, $athlete);
                    self::fail('Atleta não vê o irmão');
                } catch (NotFoundException) {
                }
                try {
                    $portal->overview($other, $guardian);
                    self::fail('Responsável não vê atleta de outra família');
                } catch (NotFoundException) {
                }
                self::assertContains($childB, array_column($portal->athletes($guardian), 'id'));
                self::assertIsArray($portal->overview($childB, $guardian)['evolution']);

                // Autoria: professor não exclui registro do admin
                $adminEval = array_values(array_filter($staff['evaluations'], static fn ($e) => $e['evaluated_by'] === $admin->id))[0];
                try {
                    $evolution->deleteEvaluation($adminEval['id'], $coach, $req);
                    self::fail('Professor não exclui avaliação de outro autor');
                } catch (ForbiddenException) {
                }

                // Meta atingida registra data; reaberta limpa
                $evolution->updateGoal($goalId, ['status' => 'atingida'], $coach, $req);
                self::assertSame(date('Y-m-d'), $db->fetchValue('SELECT achieved_at FROM athlete_goals WHERE id = ?', [$goalId]));
                $evolution->updateGoal($goalId, ['status' => 'em_andamento'], $coach, $req);
                self::assertNull($db->fetchValue('SELECT achieved_at FROM athlete_goals WHERE id = ?', [$goalId]));

                // Atleta inativo perde o acesso ao portal
                $db->execute("UPDATE athletes SET status = 'inativo' WHERE id = ?", [$childA]);
                self::assertSame([], $portal->athletes($athlete));
                self::assertFalse($policy->canView($athlete, $childA));

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertNull($db->fetchValue("SELECT id FROM users WHERE email = 'it-atleta@teste.local'"));
    }
}
