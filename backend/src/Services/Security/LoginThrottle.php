<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Exceptions\TooManyRequestsException;
use App\Repositories\Contracts\LoginAttemptRepositoryInterface;

/**
 * Proteção contra força bruta: após 5 falhas em 15 minutos (por e-mail
 * ou IP), o login fica bloqueado com espera progressiva (30s, 60s,
 * 120s... até 15 min).
 */
class LoginThrottle
{
    public const MAX_FAILURES = 5;
    public const WINDOW_SECONDS = 900;
    private const BASE_LOCK_SECONDS = 30;

    public function __construct(private LoginAttemptRepositoryInterface $attempts)
    {
    }

    /** @throws TooManyRequestsException */
    public function ensureNotLocked(string $email, string $ip): void
    {
        $failures = $this->attempts->recentFailures($email, $ip, self::WINDOW_SECONDS);
        if ($failures < self::MAX_FAILURES) {
            return;
        }

        $lockSeconds = min(self::WINDOW_SECONDS, self::BASE_LOCK_SECONDS * (2 ** ($failures - self::MAX_FAILURES)));
        $last = $this->attempts->lastFailureAt($email, $ip) ?? time();
        $remaining = $last + $lockSeconds - time();

        if ($remaining > 0) {
            throw new TooManyRequestsException(
                $remaining,
                'Muitas tentativas de login. Aguarde ' . $this->humanize($remaining) . ' e tente novamente.',
            );
        }
    }

    public function registerFailure(string $email, string $ip): void
    {
        $this->attempts->record($email, $ip, false);
    }

    public function registerSuccess(string $email, string $ip): void
    {
        $this->attempts->record($email, $ip, true);
        $this->attempts->clearFailures($email);
        if (random_int(1, 50) === 1) {
            $this->attempts->purgeOlderThan(30 * 86400);
        }
    }

    private function humanize(int $seconds): string
    {
        return $seconds >= 60 ? (int) ceil($seconds / 60) . ' min' : $seconds . ' s';
    }
}
