<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Core\Http\Request;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

/**
 * Trilha de auditoria: quem fez o quê, quando e de onde.
 * Nunca registrar senhas/tokens no payload.
 */
class AuditLogger
{
    public function __construct(private AuditLogRepositoryInterface $repository)
    {
    }

    /** @param array<string, mixed> $payload */
    public function log(
        ?Request $request,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $payload = [],
        ?int $userId = null,
    ): void {
        $this->repository->insert([
            'user_id' => $userId ?? $request?->attribute('user')?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'payload' => $payload === [] ? null : $payload,
            'ip' => $request?->ip() ?? 'cli',
            'user_agent' => $request?->userAgent() ?? 'cli',
        ]);
    }
}
