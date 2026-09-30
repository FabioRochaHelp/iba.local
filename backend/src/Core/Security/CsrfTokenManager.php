<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Session\SessionInterface;

/**
 * Synchronizer token pattern: token aleatório guardado na sessão e
 * enviado pela SPA no header X-CSRF-Token em toda requisição de escrita.
 */
final class CsrfTokenManager
{
    private const KEY = '_csrf';

    public function __construct(private SessionInterface $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = $this->regenerate();
        }

        return $token;
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);

        return $token;
    }

    public function isValid(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }
}
