<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Argon2id quando disponível na hospedagem; senão bcrypt (custo 12).
 */
final class NativePasswordHasher implements PasswordHasherInterface
{
    private string|int $algo;

    /** @var array<string, int> */
    private array $options;

    public function __construct()
    {
        if (defined('PASSWORD_ARGON2ID')) {
            $this->algo = PASSWORD_ARGON2ID;
            $this->options = ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1];
        } else {
            $this->algo = PASSWORD_BCRYPT;
            $this->options = ['cost' => 12];
        }
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algo, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo, $this->options);
    }
}
