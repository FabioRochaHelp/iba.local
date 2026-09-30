<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Services\Security\RateLimiter;

/**
 * Limite global por IP (proteção contra abuso/automação).
 * Escritas têm limite próprio, mais restrito.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = $request->ip();
        $this->limiter->attempt('ip:' . $ip, 600, 300);
        if ($request->isUnsafe()) {
            $this->limiter->attempt('write:' . $ip, 150, 300);
        }

        return $next($request);
    }
}
