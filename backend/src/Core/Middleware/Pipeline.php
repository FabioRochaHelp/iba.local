<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Executa uma cadeia de middlewares até o handler final (controller).
 */
final class Pipeline
{
    /** @param list<MiddlewareInterface> $middlewares */
    public function __construct(private array $middlewares = [])
    {
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function handle(Request $request, callable $handler): Response
    {
        $next = $handler;
        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = static fn (Request $req): Response => $middleware->process($req, $next);
        }

        return $next($request);
    }
}
