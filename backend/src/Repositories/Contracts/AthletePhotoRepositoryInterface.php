<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AthletePhotoRepositoryInterface
{
    /** @return array{mime: string, data: string, version: string}|null */
    public function find(int $athleteId): ?array;

    public function save(int $athleteId, string $mime, string $data, ?int $userId): string;

    public function delete(int $athleteId): bool;
}
