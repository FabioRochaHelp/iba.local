<?php

declare(strict_types=1);

use App\Controllers\AthleteController;
use App\Controllers\AttendanceController;
use App\Controllers\AuditController;
use App\Controllers\ClassController;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\DashboardController;
use App\Controllers\EvolutionController;
use App\Controllers\GuardianController;
use App\Controllers\HealthController;
use App\Controllers\InvoiceController;
use App\Controllers\PlanController;
use App\Controllers\PortalController;
use App\Controllers\ReportController;
use App\Controllers\SponsorController;
use App\Controllers\UniformController;
use App\Controllers\UserController;
use App\Core\Routing\Router;
use App\Models\Role;

/*
 * Política "negar por padrão": toda rota fora do grupo público precisa
 * declarar os perfis autorizados (roles). CSRF é global para escritas.
 */

$ADMIN = [Role::ADMIN];
$ALL = [Role::ADMIN, Role::PROFESSOR];                    // equipe
$PORTAL = [Role::ATHLETE, Role::GUARDIAN];                // atleta e responsável (somente leitura)
$EVERYONE = [...$ALL, ...$PORTAL];

return static function (Router $r) use ($ADMIN, $ALL, $PORTAL, $EVERYONE): void {
    $r->group(['prefix' => '/api'], function (Router $r) use ($ADMIN, $ALL, $PORTAL, $EVERYONE): void {

        // Públicas
        $r->group(['public' => true], function (Router $r): void {
            $r->get('/health', [HealthController::class, 'show']);
            $r->get('/auth/csrf', [AuthController::class, 'csrf']);
            $r->post('/auth/login', [AuthController::class, 'login']);
        });

        // Comuns a todos os usuários autenticados
        $r->group(['middleware' => ['auth'], 'roles' => $EVERYONE], function (Router $r): void {
            $r->get('/auth/me', [AuthController::class, 'me']);
            $r->post('/auth/logout', [AuthController::class, 'logout']);
            $r->put('/auth/password', [AuthController::class, 'changePassword']);
        });

        // Portal do atleta/responsável — só os atletas vinculados (verificado no service)
        $r->group(['middleware' => ['auth'], 'roles' => $PORTAL], function (Router $r): void {
            $r->get('/portal/athletes', [PortalController::class, 'athletes']);
            $r->get('/portal/athletes/{id:id}', [PortalController::class, 'overview']);
        });

        // Equipe (admin e professor)
        $r->group(['middleware' => ['auth'], 'roles' => $ALL], function (Router $r) use ($ADMIN): void {
            // Evolução — professor só de atletas das suas turmas (verificado no service)
            $r->get('/evaluation-criteria', [EvolutionController::class, 'criteria']);
            $r->post('/evaluation-criteria', [EvolutionController::class, 'storeCriterion'], ['roles' => $ADMIN]);
            $r->put('/evaluation-criteria/{id:id}', [EvolutionController::class, 'updateCriterion'], ['roles' => $ADMIN]);
            $r->get('/athletes/{id:id}/evolution', [EvolutionController::class, 'show']);
            $r->post('/athletes/{id:id}/evaluations', [EvolutionController::class, 'storeEvaluation']);
            $r->delete('/evaluations/{id:id}', [EvolutionController::class, 'destroyEvaluation']);
            $r->post('/athletes/{id:id}/measurements', [EvolutionController::class, 'storeMeasurement']);
            $r->delete('/measurements/{id:id}', [EvolutionController::class, 'destroyMeasurement']);
            $r->post('/athletes/{id:id}/notes', [EvolutionController::class, 'storeNote']);
            $r->put('/notes/{id:id}/visibility', [EvolutionController::class, 'noteVisibility']);
            $r->delete('/notes/{id:id}', [EvolutionController::class, 'destroyNote']);
            $r->post('/athletes/{id:id}/goals', [EvolutionController::class, 'storeGoal']);
            $r->put('/goals/{id:id}', [EvolutionController::class, 'updateGoal']);
            $r->delete('/goals/{id:id}', [EvolutionController::class, 'destroyGoal']);

            $r->get('/dashboard', [DashboardController::class, 'show']);

            // Catálogos
            $r->get('/positions', [CatalogController::class, 'positions']);

            // Turmas e chamada — professor só nas próprias turmas (verificado no service)
            $r->get('/classes', [ClassController::class, 'index']);
            $r->get('/classes/{id:id}', [ClassController::class, 'show']);
            $r->post('/classes', [ClassController::class, 'store'], ['roles' => $ADMIN]);
            $r->put('/classes/{id:id}', [ClassController::class, 'update'], ['roles' => $ADMIN]);
            $r->put('/classes/{id:id}/athletes', [ClassController::class, 'setAthletes'], ['roles' => $ADMIN]);
            $r->get('/classes/{id:id}/suggestions', [ClassController::class, 'suggestions'], ['roles' => $ADMIN]);
            $r->get('/coaches', [ClassController::class, 'coaches'], ['roles' => $ADMIN]);
            $r->post('/classes/{id:id}/sessions', [AttendanceController::class, 'open']);
            $r->get('/classes/{id:id}/report', [AttendanceController::class, 'classReport']);
            $r->get('/sessions/{id:id}', [AttendanceController::class, 'show']);
            $r->put('/sessions/{id:id}/attendance', [AttendanceController::class, 'save']);
            $r->get('/athletes/{id:id}/attendance', [AttendanceController::class, 'athlete']);

            // Atletas — professor só consulta (sem dados financeiros)
            $r->get('/athletes', [AthleteController::class, 'index']);
            $r->get('/athletes/{id:id}', [AthleteController::class, 'show']);
            $r->post('/athletes', [AthleteController::class, 'store'], ['roles' => $ADMIN]);
            $r->put('/athletes/{id:id}', [AthleteController::class, 'update'], ['roles' => $ADMIN]);
            $r->delete('/athletes/{id:id}', [AthleteController::class, 'destroy'], ['roles' => $ADMIN]);

            // Responsáveis — somente admin (CPF, e-mail)
            $r->group(['roles' => $ADMIN], function (Router $r): void {
                // Planos e financeiro
                $r->get('/plans', [PlanController::class, 'index']);
                $r->post('/plans', [PlanController::class, 'store']);
                $r->put('/plans/{id:id}', [PlanController::class, 'update']);

                $r->get('/invoices', [InvoiceController::class, 'index']);
                $r->post('/invoices/generate', [InvoiceController::class, 'generate']);
                $r->get('/invoices/{id:id}', [InvoiceController::class, 'show']);
                $r->put('/invoices/{id:id}', [InvoiceController::class, 'update']);
                $r->post('/invoices/{id:id}/cancel', [InvoiceController::class, 'cancel']);
                $r->post('/invoices/{id:id}/payments', [InvoiceController::class, 'pay']);
                $r->post('/payments/{id:id}/reverse', [InvoiceController::class, 'reversePayment']);
                $r->get('/athletes/{id:id}/invoices', [InvoiceController::class, 'forAthlete']);

                $r->get('/reports/delinquency', [ReportController::class, 'delinquency']);
                $r->get('/reports/monthly', [ReportController::class, 'monthly']);

                // Usuários e auditoria
                $r->get('/users', [UserController::class, 'index']);
                $r->post('/users', [UserController::class, 'store']);
                $r->put('/users/{id:id}', [UserController::class, 'update']);
                $r->post('/users/{id:id}/reset-password', [UserController::class, 'resetPassword']);
                $r->get('/audit-logs', [AuditController::class, 'index']);

                // Uniformes
                $r->get('/uniform-items', [UniformController::class, 'items']);
                $r->post('/uniform-items', [UniformController::class, 'storeItem']);
                $r->put('/uniform-items/{id:id}', [UniformController::class, 'updateItem']);
                $r->get('/uniform-orders', [UniformController::class, 'orders']);
                $r->post('/uniform-orders', [UniformController::class, 'storeOrder']);
                $r->get('/uniform-orders/{id:id}', [UniformController::class, 'showOrder']);
                $r->post('/uniform-orders/{id:id}/payments', [UniformController::class, 'pay']);
                $r->put('/uniform-orders/{id:id}/delivery', [UniformController::class, 'deliver']);
                $r->post('/uniform-orders/{id:id}/cancel', [UniformController::class, 'cancel']);

                // Patrocínios
                $r->get('/sponsors', [SponsorController::class, 'index']);
                $r->post('/sponsors', [SponsorController::class, 'store']);
                $r->put('/sponsors/{id:id}', [SponsorController::class, 'update']);
                $r->delete('/sponsors/{id:id}', [SponsorController::class, 'destroy']);
                $r->get('/sponsorships', [SponsorController::class, 'entries']);
                $r->post('/sponsorships', [SponsorController::class, 'storeEntry']);
                $r->post('/sponsorships/{id:id}/delete', [SponsorController::class, 'destroyEntry']);
                $r->get('/sponsorships/summary', [SponsorController::class, 'summary']);

                $r->get('/guardians', [GuardianController::class, 'index']);
                $r->get('/guardians/{id:id}', [GuardianController::class, 'show']);
                $r->post('/guardians', [GuardianController::class, 'store']);
                $r->put('/guardians/{id:id}', [GuardianController::class, 'update']);
                $r->delete('/guardians/{id:id}', [GuardianController::class, 'destroy']);
            });
        });
    });
};
