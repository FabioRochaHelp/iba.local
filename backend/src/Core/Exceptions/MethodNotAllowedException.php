<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Método não permitido.')
    {
        parent::__construct(405, $message);
    }
}
