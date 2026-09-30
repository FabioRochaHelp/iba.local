<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Sessão em memória — usada nos testes automatizados.
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public int $regenerations = 0;

    public function start(string $ip, string $userAgent): void
    {
    }

    public function id(): string
    {
        return 'array-session-' . $this->regenerations;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function invalidate(): void
    {
        $this->data = [];
        $this->regenerations++;
    }
}
