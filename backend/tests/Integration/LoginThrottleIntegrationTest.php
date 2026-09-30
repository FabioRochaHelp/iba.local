<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Exceptions\TooManyRequestsException;
use App\Repositories\Pdo\PdoLoginAttemptRepository;
use App\Services\Security\LoginThrottle;

final class LoginThrottleIntegrationTest extends DatabaseTestCase
{
    private const EMAIL = 'it-throttle@teste.local';
    private const IP = '203.0.113.77';

    protected function tearDown(): void
    {
        self::$db?->execute('DELETE FROM login_attempts WHERE email = ? OR ip = ?', [self::EMAIL, self::IP]);
    }

    public function testLocksAfterFailuresEvenInSameSecondAsPreviousSuccess(): void
    {
        $repo = new PdoLoginAttemptRepository(self::$db);
        $throttle = new LoginThrottle($repo);

        // Regressão: sucesso e falhas no mesmo segundo não podem zerar a contagem.
        $throttle->registerSuccess(self::EMAIL, self::IP);
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            $throttle->registerFailure(self::EMAIL, self::IP);
        }

        $this->expectException(TooManyRequestsException::class);
        $throttle->ensureNotLocked(self::EMAIL, self::IP);
    }

    public function testSqlInjectionInEmailIsTreatedAsLiteral(): void
    {
        $repo = new PdoLoginAttemptRepository(self::$db);
        $repo->record(self::EMAIL, self::IP, false);

        self::assertSame(0, $repo->recentFailures("' OR '1'='1", '0.0.0.0', 900));
        self::assertSame(1, $repo->recentFailures(self::EMAIL, '0.0.0.0', 900));
    }
}
