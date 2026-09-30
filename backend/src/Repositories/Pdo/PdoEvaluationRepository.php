<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database\Connection;
use App\Repositories\Contracts\EvaluationRepositoryInterface;

final class PdoEvaluationRepository implements EvaluationRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function criteria(bool $onlyActive = false): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'category' => $r['category'],
            'sort_order' => (int) $r['sort_order'],
            'active' => (bool) $r['active'],
        ], $this->db->fetchAll(
            'SELECT id, name, category, sort_order, active FROM evaluation_criteria '
            . ($onlyActive ? 'WHERE active = 1 ' : '')
            . "ORDER BY FIELD(category, 'tecnico', 'tatico', 'fisico', 'comportamental'), sort_order, name",
        ));
    }

    public function saveCriterion(?int $id, array $data): int
    {
        $data = array_intersect_key($data, array_flip(['name', 'category', 'sort_order', 'active']));
        if (array_key_exists('active', $data)) {
            $data['active'] = (int) $data['active'];
        }
        if ($id === null) {
            return $this->db->insert('evaluation_criteria', $data);
        }
        $this->db->update('evaluation_criteria', $data, ['id' => $id]);

        return $id;
    }

    public function criterionNameExists(string $name, ?int $exceptId = null): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM evaluation_criteria WHERE name = ? AND id <> ?', [$name, $exceptId ?? 0]) !== null;
    }

    public function create(array $data, array $scores): int
    {
        $id = $this->db->insert('evaluations', [
            'athlete_id' => $data['athlete_id'],
            'evaluated_by' => $data['evaluated_by'],
            'evaluation_date' => $data['evaluation_date'],
            'general_comment' => $data['general_comment'],
            'visible_to_athlete' => (int) $data['visible_to_athlete'],
        ]);
        foreach ($scores as $criterionId => $score) {
            $this->db->insert('evaluation_scores', ['evaluation_id' => $id, 'criterion_id' => (int) $criterionId, 'score' => (int) $score]);
        }

        return $id;
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, athlete_id, evaluated_by FROM evaluations WHERE id = ?', [$id]);
    }

    public function forAthlete(int $athleteId, bool $onlyVisible = false): array
    {
        $rows = $this->db->fetchAll(
            'SELECT e.id, e.evaluation_date, e.general_comment, e.visible_to_athlete, e.evaluated_by, u.name AS evaluator_name
               FROM evaluations e LEFT JOIN users u ON u.id = e.evaluated_by
              WHERE e.athlete_id = ?' . ($onlyVisible ? ' AND e.visible_to_athlete = 1' : '') . '
              ORDER BY e.evaluation_date DESC, e.id DESC',
            [$athleteId],
        );
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $scores = [];
        foreach ($this->db->fetchAll("SELECT evaluation_id, criterion_id, score FROM evaluation_scores WHERE evaluation_id IN ({$in})", $ids) as $s) {
            $scores[(int) $s['evaluation_id']][(int) $s['criterion_id']] = (int) $s['score'];
        }

        return array_map(static function (array $r) use ($scores): array {
            $own = $scores[(int) $r['id']] ?? [];

            return [
                'id' => (int) $r['id'],
                'evaluation_date' => $r['evaluation_date'],
                'general_comment' => $r['general_comment'],
                'visible_to_athlete' => (bool) $r['visible_to_athlete'],
                'evaluated_by' => $r['evaluated_by'] !== null ? (int) $r['evaluated_by'] : null,
                'evaluator_name' => $r['evaluator_name'],
                'scores' => $own,
                'average' => $own === [] ? null : round(array_sum($own) / count($own), 2),
            ];
        }, $rows);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM evaluations WHERE id = ?', [$id]);
    }
}
