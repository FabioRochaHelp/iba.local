<?php

declare(strict_types=1);

/**
 * Ligações interface → implementação (Inversão de Dependência).
 * Trocar a persistência (ex.: repositório em memória nos testes)
 * não exige alterar nenhum Service.
 */

use App\Core\Config\Config;
use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Http\ExceptionHandler;
use App\Core\Logging\FileLogger;
use App\Core\Logging\LoggerInterface;
use App\Core\Session\DatabaseSessionHandler;
use App\Core\Session\NativeSession;
use App\Core\Session\SessionInterface;
use App\Repositories\Contracts;
use App\Repositories\Pdo;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Discounts\DiscountCalculator;
use App\Services\Import\AthleteImportService;
use App\Services\Import\CsvReader;
use App\Services\Import\SpreadsheetRowParser;
use App\Services\Import\XlsxReader;
use App\Services\Security\NativePasswordHasher;
use App\Services\Security\PasswordHasherInterface;

return static function (Container $c, Config $config): void {
    $c->instance(Config::class, $config);

    // Infraestrutura
    $c->singleton(Connection::class, fn () => new Connection($config->get('database')));
    $c->singleton(LoggerInterface::class, fn () => new FileLogger($config->get('paths.logs')));
    $c->singleton(ExceptionHandler::class, fn (Container $c) => new ExceptionHandler(
        $c->get(LoggerInterface::class),
        (bool) $config->get('app.debug'),
    ));
    $c->singleton(SessionInterface::class, fn (Container $c) => new NativeSession(
        new DatabaseSessionHandler($c->get(Connection::class), (int) $config->get('session.absolute_hours') * 3600),
        $config->get('session'),
    ));
    $c->singleton(PasswordHasherInterface::class, NativePasswordHasher::class);

    // Repositórios
    $repositories = [
        Contracts\UserRepositoryInterface::class => Pdo\PdoUserRepository::class,
        Contracts\LoginAttemptRepositoryInterface::class => Pdo\PdoLoginAttemptRepository::class,
        Contracts\RateLimitRepositoryInterface::class => Pdo\PdoRateLimitRepository::class,
        Contracts\AuditLogRepositoryInterface::class => Pdo\PdoAuditLogRepository::class,
        Contracts\PositionRepositoryInterface::class => Pdo\PdoPositionRepository::class,
        Contracts\PlanRepositoryInterface::class => Pdo\PdoPlanRepository::class,
        Contracts\AthletePlanRepositoryInterface::class => Pdo\PdoAthletePlanRepository::class,
        Contracts\GuardianRepositoryInterface::class => Pdo\PdoGuardianRepository::class,
        Contracts\AthleteRepositoryInterface::class => Pdo\PdoAthleteRepository::class,
        Contracts\InvoiceRepositoryInterface::class => Pdo\PdoInvoiceRepository::class,
        Contracts\PaymentRepositoryInterface::class => Pdo\PdoPaymentRepository::class,
        Contracts\UniformRepositoryInterface::class => Pdo\PdoUniformRepository::class,
        Contracts\SponsorshipRepositoryInterface::class => Pdo\PdoSponsorshipRepository::class,
        Contracts\SponsorRepositoryInterface::class => Pdo\PdoSponsorRepository::class,
        Contracts\ClassRepositoryInterface::class => Pdo\PdoClassRepository::class,
        Contracts\AttendanceRepositoryInterface::class => Pdo\PdoAttendanceRepository::class,
        Contracts\EvaluationRepositoryInterface::class => Pdo\PdoEvaluationRepository::class,
        Contracts\MeasurementRepositoryInterface::class => Pdo\PdoMeasurementRepository::class,
        Contracts\CoachNoteRepositoryInterface::class => Pdo\PdoCoachNoteRepository::class,
        Contracts\GoalRepositoryInterface::class => Pdo\PdoGoalRepository::class,
    ];
    foreach ($repositories as $contract => $implementation) {
        $c->singleton($contract, $implementation);
    }

    // Descontos: novas regras entram aqui (Aberto/Fechado).
    $c->singleton(DiscountCalculator::class, fn () => DiscountCalculator::default());

    // Importação: leitores de planilha disponíveis (Aberto/Fechado — novo formato = novo leitor).
    $c->singleton(AthleteImportService::class, fn (Container $c) => new AthleteImportService(
        [new CsvReader(), new XlsxReader()],
        $c->get(SpreadsheetRowParser::class),
        $c->get(Connection::class),
        $c->get(Contracts\AthleteRepositoryInterface::class),
        $c->get(Contracts\GuardianRepositoryInterface::class),
        $c->get(Contracts\PositionRepositoryInterface::class),
        $c->get(Contracts\PlanRepositoryInterface::class),
        $c->get(Contracts\AthletePlanRepositoryInterface::class),
        $c->get(Contracts\InvoiceRepositoryInterface::class),
        $c->get(Contracts\PaymentRepositoryInterface::class),
        $c->get(Contracts\UniformRepositoryInterface::class),
        $c->get(Contracts\SponsorshipRepositoryInterface::class),
        $c->get(AuditLogger::class),
    ));
};
