<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\RateLimitRepositoryInterface;

final class PdoRateLimitRepository implements RateLimitRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function hit(string $key, int $windowSeconds): array
    {
        $now = time();
        $hash = hash('sha256', $key);

        // Janela fixa: reinicia o contador quando a janela expira.
        $this->db->execute(
            'INSERT INTO rate_limits (key_hash, hits, window_start) VALUES (:k, 1, :now)
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start <= :expired, 1, hits + 1),
                window_start = IF(window_start <= :expired2, :now2, window_start)',
            ['k' => $hash, 'now' => $now, 'expired' => $now - $windowSeconds, 'expired2' => $now - $windowSeconds, 'now2' => $now],
        );

        $row = $this->db->fetchOne('SELECT hits, window_start FROM rate_limits WHERE key_hash = ?', [$hash]);

        return [(int) ($row['hits'] ?? 1), max(1, (int) ($row['window_start'] ?? $now) + $windowSeconds - $now)];
    }
}
