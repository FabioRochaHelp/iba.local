<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\ExceptionHandler;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private ExceptionHandler $handler)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handler->render($e, $request);
        }
    }
}
