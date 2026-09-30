<?php

declare(strict_types=1);

namespace App\Core\Session;

interface SessionInterface
{
    public function start(string $ip, string $userAgent): void;

    public function id(): string;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Gera novo ID de sessão (proteção contra fixação de sessão). */
    public function regenerate(): void;

    /** Limpa os dados e invalida a sessão atual. */
    public function invalidate(): void;
}
