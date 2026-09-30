<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class TooManyRequestsException extends HttpException
{
    public function __construct(int $retryAfterSeconds, string $message = 'Muitas tentativas. Aguarde e tente novamente.')
    {
        parent::__construct(429, $message, ['Retry-After' => (string) max(1, $retryAfterSeconds)]);
    }
}
