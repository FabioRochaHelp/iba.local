<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface LoginAttemptRepositoryInterface
{
    public function record(string $email, string $ip, bool $success): void;

    /** Falhas desde o último sucesso, dentro da janela, por e-mail OU IP. */
    public function recentFailures(string $email, string $ip, int $windowSeconds): int;

    public function lastFailureAt(string $email, string $ip): ?int;

    public function clearFailures(string $email): void;

    public function purgeOlderThan(int $seconds): void;
}
