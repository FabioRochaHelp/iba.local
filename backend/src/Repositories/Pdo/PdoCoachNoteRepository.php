<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\CoachNoteRepositoryInterface;

final class PdoCoachNoteRepository implements CoachNoteRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert('coach_notes', [
            'athlete_id' => $data['athlete_id'],
            'author_id' => $data['author_id'],
            'note_date' => $data['note_date'],
            'type' => $data['type'],
            'content' => $data['content'],
            'visible_to_athlete' => (int) $data['visible_to_athlete'],
        ]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, athlete_id, author_id FROM coach_notes WHERE id = ?', [$id]);
    }

    public function forAthlete(int $athleteId, bool $onlyVisible = false): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'note_date' => $r['note_date'],
            'type' => $r['type'],
            'content' => $r['content'],
            'visible_to_athlete' => (bool) $r['visible_to_athlete'],
            'author_id' => $r['author_id'] !== null ? (int) $r['author_id'] : null,
            'author_name' => $r['author_name'],
        ], $this->db->fetchAll(
            'SELECT n.*, u.name AS author_name FROM coach_notes n LEFT JOIN users u ON u.id = n.author_id
              WHERE n.athlete_id = ?' . ($onlyVisible ? ' AND n.visible_to_athlete = 1' : '') . '
              ORDER BY n.note_date DESC, n.id DESC',
            [$athleteId],
        ));
    }

    public function setVisibility(int $id, bool $visible): void
    {
        $this->db->update('coach_notes', ['visible_to_athlete' => (int) $visible], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM coach_notes WHERE id = ?', [$id]);
    }
}
