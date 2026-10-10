<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\AthletePhotoRepositoryInterface;

final class PdoAthletePhotoRepository implements AthletePhotoRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function find(int $athleteId): ?array
    {
        $row = $this->db->fetchOne('SELECT mime, data, version FROM athlete_photos WHERE athlete_id = ?', [$athleteId]);

        return $row === null ? null : ['mime' => $row['mime'], 'data' => $row['data'], 'version' => $row['version']];
    }

    public function save(int $athleteId, string $mime, string $data, ?int $userId): string
    {
        $version = substr(hash('sha256', $data), 0, 12);
        $this->db->execute(
            'INSERT INTO athlete_photos (athlete_id, mime, data, bytes, version, updated_by)
             VALUES (:id, :mime, :data, :bytes, :version, :user)
             ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data), bytes = VALUES(bytes),
                                     version = VALUES(version), updated_by = VALUES(updated_by)',
            ['id' => $athleteId, 'mime' => $mime, 'data' => $data, 'bytes' => strlen($data), 'version' => $version, 'user' => $userId],
        );

        return $version;
    }

    public function delete(int $athleteId): bool
    {
        return $this->db->execute('DELETE FROM athlete_photos WHERE athlete_id = ?', [$athleteId]) > 0;
    }
}
