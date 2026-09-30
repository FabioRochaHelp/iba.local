<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Security\PasswordHasherInterface;

/** Hasher rápido para testes (bcrypt custo mínimo). */
final class FastPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return false;
    }
}
