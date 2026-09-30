<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface GuardianRepositoryInterface
{
    /**
     * @param array{search?: ?string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** @return array<string, mixed>|null com phones e athletes */
    public function find(int $id): ?array;

    public function findIdByPhone(string $phone): ?int;

    public function findIdByName(string $name): ?int;

    /** @param array{name: string, cpf?: ?string, email?: ?string, notes?: ?string} $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void;

    /** @param list<array{phone: string, is_whatsapp?: bool, label?: ?string}> $phones */
    public function syncPhones(int $id, array $phones): void;

    public function delete(int $id): void;

    public function athletesCount(int $id): int;

    public function cpfExists(string $cpf, ?int $exceptId = null): bool;
}
