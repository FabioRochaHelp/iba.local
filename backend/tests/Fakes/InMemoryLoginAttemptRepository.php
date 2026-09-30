<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Repositories\Contracts\LoginAttemptRepositoryInterface;

final class InMemoryLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /** @var list<array{email: string, ip: string, success: bool, at: int}> */
    public array $attempts = [];

    public function record(string $email, string $ip, bool $success): void
    {
        $this->attempts[] = ['email' => $email, 'ip' => $ip, 'success' => $success, 'at' => time()];
    }

    public function recentFailures(string $email, string $ip, int $windowSeconds): int
    {
        $since = time() - $windowSeconds;

        return count(array_filter(
            $this->attempts,
            static fn ($a) => !$a['success'] && $a['at'] >= $since && ($a['email'] === $email || $a['ip'] === $ip),
        ));
    }

    public function lastFailureAt(string $email, string $ip): ?int
    {
        $times = array_column(array_filter($this->attempts, static fn ($a) => !$a['success']), 'at');

        return $times === [] ? null : max($times);
    }

    public function clearFailures(string $email): void
    {
        $this->attempts = array_values(array_filter($this->attempts, static fn ($a) => $a['success'] || $a['email'] !== $email));
    }

    public function purgeOlderThan(int $seconds): void
    {
    }
}
