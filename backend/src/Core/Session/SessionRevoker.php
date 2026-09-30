<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Database\Connection;

/**
 * Encerra sessões ativas de um usuário (troca de senha, desativação).
 */
class SessionRevoker
{
    public function __construct(private Connection $db)
    {
    }

    public function revokeUser(int $userId, ?string $exceptSessionId = null): void
    {
        $this->db->execute(
            'DELETE FROM sessions WHERE user_id = ? AND id <> ?',
            [$userId, $exceptSessionId !== null ? hash('sha256', $exceptSessionId) : ''],
        );
    }
}
