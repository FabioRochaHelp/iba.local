<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Session\SessionInterface;

final class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionInterface $session)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->session->start($request->ip(), $request->userAgent());

        return $next($request);
    }
}
