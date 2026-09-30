<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Sessão PHP nativa com cookie endurecido e expiração por inatividade
 * e absoluta. A sessão fica vinculada ao user-agent do navegador.
 */
final class NativeSession implements SessionInterface
{
    private bool $started = false;

    /**
     * @param array{name: string, secure: bool, idle_minutes: int, absolute_hours: int} $config
     */
    public function __construct(
        private DatabaseSessionHandler $handler,
        private array $config,
    ) {
    }

    public function start(string $ip, string $userAgent): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        ini_set('session.use_strict_mode', '1');      // rejeita IDs não emitidos pelo servidor
        ini_set('session.use_only_cookies', '1');     // nunca aceitar ID pela URL
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        $this->handler->setClientInfo($ip, $userAgent);
        session_set_save_handler($this->handler, true);
        session_name($this->config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->config['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        $this->started = true;

        $this->enforcePolicies($userAgent);
    }

    public function id(): string
    {
        return (string) session_id();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $this->touch(true);
    }

    private function enforcePolicies(string $userAgent): void
    {
        $now = time();
        $uaHash = hash('sha256', $userAgent);
        $created = (int) ($_SESSION['_created'] ?? 0);
        $last = (int) ($_SESSION['_last'] ?? 0);

        $expired = $created > 0 && (
            $now - $last > $this->config['idle_minutes'] * 60
            || $now - $created > $this->config['absolute_hours'] * 3600
        );
        $hijacked = isset($_SESSION['_ua']) && !hash_equals((string) $_SESSION['_ua'], $uaHash);

        if ($expired || $hijacked) {
            $this->invalidate();
        }

        $this->touch($created === 0);
        $_SESSION['_ua'] = $uaHash;
    }

    private function touch(bool $isNew): void
    {
        if ($isNew || !isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
        $_SESSION['_last'] = time();
    }
}
