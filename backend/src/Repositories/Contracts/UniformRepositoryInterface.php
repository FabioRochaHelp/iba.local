<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UniformRepositoryInterface
{
    // Itens
    public function findItemIdByName(string $name): ?int;

    public function createItem(string $name, string $price): int;

    /** @return list<array<string, mixed>> */
    public function items(bool $onlyActive = false): array;

    /** @return array<string, mixed>|null */
    public function findItem(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function saveItem(?int $id, array $data): int;

    public function itemNameExists(string $name, ?int $exceptId = null): bool;

    // Pedidos
    /** @param array<string, mixed> $data */
    public function createOrder(array $data): int;

    /** @return array<string, mixed>|null */
    public function findOrder(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function lockOrder(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function updateOrder(int $id, array $data): void;

    /**
     * @param array{search?: ?string, status?: ?string, delivery?: ?string, athlete_id?: ?int, item_id?: ?int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginateOrders(array $filters, int $page, int $perPage): array;

    /** @return array<string, mixed> */
    public function summary(): array;
}
