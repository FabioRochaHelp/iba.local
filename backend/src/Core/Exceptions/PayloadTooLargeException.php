<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class PayloadTooLargeException extends HttpException
{
    public function __construct(string $message = 'Requisição muito grande.')
    {
        parent::__construct(413, $message);
    }
}
