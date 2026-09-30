<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class CsrfException extends HttpException
{
    public function __construct(string $message = 'Sessão expirada ou token de segurança inválido. Recarregue a página.')
    {
        parent::__construct(419, $message);
    }
}
