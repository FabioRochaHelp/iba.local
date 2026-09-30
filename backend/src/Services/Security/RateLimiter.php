<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Exceptions\TooManyRequestsException;
use App\Repositories\Contracts\RateLimitRepositoryInterface;

class RateLimiter
{
    public function __construct(private RateLimitRepositoryInterface $store)
    {
    }

    /** @throws TooManyRequestsException */
    public function attempt(string $key, int $maxHits, int $windowSeconds): void
    {
        [$hits, $retryAfter] = $this->store->hit($key, $windowSeconds);
        if ($hits > $maxHits) {
            throw new TooManyRequestsException($retryAfter);
        }
    }
}
