<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> inclui estornados (com reversed_at) */
    public function forInvoice(int $invoiceId): array;

    /** Soma dos pagamentos não estornados. */
    public function activeTotalForInvoice(int $invoiceId): string;

    /** @return list<array<string, mixed>> */
    public function forUniformOrder(int $orderId): array;

    public function activeTotalForUniformOrder(int $orderId): string;

    public function reverse(int $id, int $userId, string $reason): void;

    /** Total recebido (regime de caixa) entre as datas. */
    public function receivedBetween(string $from, string $to): string;
}
