<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors campo => mensagem */
    public function __construct(private array $errors, string $message = 'Dados inválidos.')
    {
        parent::__construct(422, $message);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function withField(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}
