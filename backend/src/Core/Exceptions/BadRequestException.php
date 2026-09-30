<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Requisição inválida.')
    {
        parent::__construct(400, $message);
    }
}
