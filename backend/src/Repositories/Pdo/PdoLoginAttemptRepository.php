<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\LoginAttemptRepositoryInterface;

final class PdoLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $this->db->insert('login_attempts', [
            'email' => mb_substr(mb_strtolower($email), 0, 190),
            'ip' => mb_substr($ip, 0, 45),
            'success' => (int) $success,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recentFailures(string $email, string $ip, int $windowSeconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);

        // Um login bem-sucedido apaga as falhas do e-mail (clearFailures),
        // então basta contar as falhas dentro da janela.
        $byEmail = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at >= ?',
            [mb_strtolower($email), $since],
        );
        // Por IP o limite é mais largo (vários usuários podem compartilhar IP).
        $byIp = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at >= ?',
            [$ip, $since],
        );

        return max($byEmail, intdiv($byIp, 3));
    }

    public function lastFailureAt(string $email, string $ip): ?int
    {
        $value = $this->db->fetchValue(
            'SELECT MAX(attempted_at) FROM login_attempts WHERE (email = ? OR ip = ?) AND success = 0',
            [mb_strtolower($email), $ip],
        );

        return $value ? (int) strtotime((string) $value) : null;
    }

    public function clearFailures(string $email): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE email = ? AND success = 0', [mb_strtolower($email)]);
    }

    public function purgeOlderThan(int $seconds): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - $seconds)]);
    }
}
