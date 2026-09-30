<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

final class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function insert(array $entry): void
    {
        $this->db->insert('audit_logs', [
            'user_id' => $entry['user_id'] ?? null,
            'action' => mb_substr((string) $entry['action'], 0, 60),
            'entity' => mb_substr((string) ($entry['entity'] ?? ''), 0, 60),
            'entity_id' => $entry['entity_id'] ?? null,
            'payload' => isset($entry['payload']) ? json_encode($entry['payload'], JSON_UNESCAPED_UNICODE) : null,
            'ip' => mb_substr((string) ($entry['ip'] ?? ''), 0, 45),
            'user_agent' => mb_substr((string) ($entry['user_agent'] ?? ''), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['entity'])) {
            $where[] = 'a.entity = :entity';
            $params['entity'] = $filters['entity'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}", $params);
        $items = $this->db->fetchAll(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.payload, a.ip, a.created_at, u.name AS user_name
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE {$whereSql} ORDER BY a.id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );
        foreach ($items as &$item) {
            $item['payload'] = $item['payload'] ? json_decode((string) $item['payload'], true) : null;
        }

        return ['items' => $items, 'total' => $total];
    }
}
