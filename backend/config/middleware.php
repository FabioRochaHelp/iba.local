<?php

declare(strict_types=1);

use App\Middleware;

return [
    // Ordem de execução (de fora para dentro) em TODAS as requisições.
    'global' => [
        Middleware\SecurityHeadersMiddleware::class,
        Middleware\ErrorHandlerMiddleware::class,
        Middleware\JsonBodyGuardMiddleware::class,
        Middleware\RateLimitMiddleware::class,
        Middleware\StartSessionMiddleware::class,
        Middleware\CsrfMiddleware::class,
    ],
    // Aliases usados nas rotas.
    'aliases' => [
        'auth' => Middleware\AuthMiddleware::class,
    ],
];
