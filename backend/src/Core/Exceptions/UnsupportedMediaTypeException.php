<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class UnsupportedMediaTypeException extends HttpException
{
    public function __construct(string $message = 'Envie os dados em formato JSON.')
    {
        parent::__construct(415, $message);
    }
}
