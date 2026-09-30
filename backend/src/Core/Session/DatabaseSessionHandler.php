<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Database\Connection;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Armazena sessões no MySQL (evita o /tmp compartilhado da hospedagem).
 * O ID da sessão é guardado como hash SHA-256: um vazamento do banco
 * não permite sequestrar sessões.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private string $ip = '';

    private string $userAgent = '';

    public function __construct(
        private Connection $db,
        private int $lifetimeSeconds,
    ) {
    }

    public function setClientInfo(string $ip, string $userAgent): void
    {
        $this->ip = mb_substr($ip, 0, 45);
        $this->userAgent = mb_substr($userAgent, 0, 255);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $payload = $this->db->fetchValue(
            'SELECT payload FROM sessions WHERE id = ? AND last_activity > ?',
            [$this->hash($id), time() - $this->lifetimeSeconds],
        );

        return is_string($payload) ? $payload : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = isset($_SESSION['_uid']) ? (int) $_SESSION['_uid'] : null;

        $this->db->execute(
            'INSERT INTO sessions (id, user_id, payload, ip, user_agent, last_activity)
             VALUES (:id, :uid, :payload, :ip, :ua, :ts)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload),
                 ip = VALUES(ip), user_agent = VALUES(user_agent), last_activity = VALUES(last_activity)',
            [
                'id' => $this->hash($id),
                'uid' => $userId,
                'payload' => $data,
                'ip' => $this->ip,
                'ua' => $this->userAgent,
                'ts' => time(),
            ],
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->execute('DELETE FROM sessions WHERE id = ?', [$this->hash($id)]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->db->execute('DELETE FROM sessions WHERE last_activity < ?', [time() - $this->lifetimeSeconds]);
    }

    public function validateId(string $id): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM sessions WHERE id = ? AND last_activity > ?',
            [$this->hash($id), time() - $this->lifetimeSeconds],
        ) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->execute('UPDATE sessions SET last_activity = ? WHERE id = ?', [time(), $this->hash($id)]);

        return true;
    }

    private function hash(string $id): string
    {
        return hash('sha256', $id);
    }
}
