<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use App\Core\Database\Connection;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\CoachNoteRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use App\Repositories\Contracts\GoalRepositoryInterface;
use App\Repositories\Contracts\MeasurementRepositoryInterface;
use App\Services\Audit\AuditLogger;

/**
 * Evolução do atleta: avaliações técnicas, medidas físicas,
 * observações do professor e metas.
 *
 * Visibilidade para o portal (atleta/responsável):
 *  - avaliações: só as marcadas como visíveis (padrão: visível);
 *  - observações: só as marcadas como visíveis (padrão: oculta);
 *  - medidas e metas: sempre visíveis.
 * Exclusão: admin exclui qualquer registro; professor só os próprios.
 */
class EvolutionService
{
    public const NOTE_TYPES = ['ponto_forte', 'a_melhorar', 'comportamento', 'geral'];
    public const GOAL_STATUSES = ['em_andamento', 'atingida', 'cancelada'];

    public function __construct(
        private AthleteAccessPolicy $policy,
        private EvaluationRepositoryInterface $evaluations,
        private MeasurementRepositoryInterface $measurements,
        private CoachNoteRepositoryInterface $notes,
        private GoalRepositoryInterface $goals,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(int $athleteId, User $viewer): array
    {
        $this->policy->authorizeView($viewer, $athleteId);
        $portal = !$viewer->isStaff();

        return [
            'can_edit' => $this->policy->canEdit($viewer, $athleteId),
            'criteria' => $this->evaluations->criteria(),
            'evaluations' => $this->evaluations->forAthlete($athleteId, $portal),
            'measurements' => $this->measurements->forAthlete($athleteId),
            'notes' => $this->notes->forAthlete($athleteId, $portal),
            'goals' => $this->goals->forAthlete($athleteId),
        ];
    }

    // ---------- Critérios (admin) ----------

    /** @return list<array<string, mixed>> */
    public function criteria(bool $onlyActive = false): array
    {
        return $this->evaluations->criteria($onlyActive);
    }

    /** @param array<string, mixed> $data */
    public function saveCriterion(?int $id, array $data, Request $request): int
    {
        if (isset($data['name']) && $this->evaluations->criterionNameExists($data['name'], $id)) {
            throw ValidationException::withField('name', 'Já existe um critério com este nome.');
        }
        $saved = $this->evaluations->saveCriterion($id, $data);
        $this->audit->log($request, $id === null ? 'criterion_created' : 'criterion_updated', 'evaluation_criterion', $saved);

        return $saved;
    }

    // ---------- Avaliações ----------

    /**
     * @param array{evaluation_date: string, general_comment?: ?string, visible_to_athlete?: bool, scores: list<array{criterion_id: int, score: int}>} $data
     */
    public function addEvaluation(int $athleteId, array $data, User $user, Request $request): int
    {
        $this->policy->authorizeEdit($user, $athleteId);
        $this->assertNotFuture($data['evaluation_date'], 'evaluation_date');

        $active = array_column($this->evaluations->criteria(true), 'id');
        $scores = [];
        foreach ($data['scores'] as $i => $item) {
            if (!in_array($item['criterion_id'], $active, true)) {
                throw ValidationException::withField("scores.{$i}.criterion_id", 'Critério inválido ou inativo.');
            }
            $scores[$item['criterion_id']] = $item['score'];
        }
        if ($scores === []) {
            throw ValidationException::withField('scores', 'Dê nota para ao menos um critério.');
        }

        $id = $this->db->transaction(fn () => $this->evaluations->create([
            'athlete_id' => $athleteId,
            'evaluated_by' => $user->id,
            'evaluation_date' => $data['evaluation_date'],
            'general_comment' => $data['general_comment'] ?? null,
            'visible_to_athlete' => $data['visible_to_athlete'] ?? true,
        ], $scores));
        $this->audit->log($request, 'evaluation_created', 'athlete', $athleteId, ['evaluation_id' => $id, 'criteria' => count($scores)]);

        return $id;
    }

    public function deleteEvaluation(int $id, User $user, Request $request): void
    {
        $row = $this->evaluations->find($id) ?? throw new NotFoundException('Avaliação não encontrada.');
        $this->assertCanDelete($user, (int) $row['athlete_id'], $row['evaluated_by']);
        $this->evaluations->delete($id);
        $this->audit->log($request, 'evaluation_deleted', 'athlete', (int) $row['athlete_id'], ['evaluation_id' => $id]);
    }

    // ---------- Medidas ----------

    /** @param array<string, mixed> $data */
    public function addMeasurement(int $athleteId, array $data, User $user, Request $request): int
    {
        $this->policy->authorizeEdit($user, $athleteId);
        $this->assertNotFuture($data['measured_at'], 'measured_at');
        $fields = ['height_cm', 'weight_kg', 'sprint_20m_s', 'vertical_jump_cm', 'endurance_m'];
        if (array_filter(array_intersect_key($data, array_flip($fields)), static fn ($v) => $v !== null) === []) {
            throw ValidationException::withField('height_cm', 'Informe ao menos uma medida.');
        }

        $id = $this->measurements->create($data + ['athlete_id' => $athleteId, 'recorded_by' => $user->id]);
        $this->audit->log($request, 'measurement_created', 'athlete', $athleteId, ['measurement_id' => $id]);

        return $id;
    }

    public function deleteMeasurement(int $id, User $user, Request $request): void
    {
        $row = $this->measurements->find($id) ?? throw new NotFoundException('Medida não encontrada.');
        $this->assertCanDelete($user, (int) $row['athlete_id'], $row['recorded_by']);
        $this->measurements->delete($id);
        $this->audit->log($request, 'measurement_deleted', 'athlete', (int) $row['athlete_id'], ['measurement_id' => $id]);
    }

    // ---------- Observações ----------

    /** @param array{note_date: string, type: string, content: string, visible_to_athlete?: bool} $data */
    public function addNote(int $athleteId, array $data, User $user, Request $request): int
    {
        $this->policy->authorizeEdit($user, $athleteId);
        $this->assertNotFuture($data['note_date'], 'note_date');
        $id = $this->notes->create($data + ['athlete_id' => $athleteId, 'author_id' => $user->id, 'visible_to_athlete' => $data['visible_to_athlete'] ?? false]);
        $this->audit->log($request, 'coach_note_created', 'athlete', $athleteId, ['note_id' => $id, 'visible' => (bool) ($data['visible_to_athlete'] ?? false)]);

        return $id;
    }

    public function setNoteVisibility(int $id, bool $visible, User $user, Request $request): void
    {
        $row = $this->notes->find($id) ?? throw new NotFoundException('Observação não encontrada.');
        $this->assertCanDelete($user, (int) $row['athlete_id'], $row['author_id']);
        $this->notes->setVisibility($id, $visible);
        $this->audit->log($request, 'coach_note_visibility', 'athlete', (int) $row['athlete_id'], ['note_id' => $id, 'visible' => $visible]);
    }

    public function deleteNote(int $id, User $user, Request $request): void
    {
        $row = $this->notes->find($id) ?? throw new NotFoundException('Observação não encontrada.');
        $this->assertCanDelete($user, (int) $row['athlete_id'], $row['author_id']);
        $this->notes->delete($id);
        $this->audit->log($request, 'coach_note_deleted', 'athlete', (int) $row['athlete_id'], ['note_id' => $id]);
    }

    // ---------- Metas ----------

    /** @param array{title: string, description?: ?string, target_date?: ?string} $data */
    public function addGoal(int $athleteId, array $data, User $user, Request $request): int
    {
        $this->policy->authorizeEdit($user, $athleteId);
        $id = $this->goals->create($data + ['athlete_id' => $athleteId, 'created_by' => $user->id, 'status' => 'em_andamento']);
        $this->audit->log($request, 'goal_created', 'athlete', $athleteId, ['goal_id' => $id]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function updateGoal(int $id, array $data, User $user, Request $request): void
    {
        $row = $this->goals->find($id) ?? throw new NotFoundException('Meta não encontrada.');
        // Qualquer avaliador do atleta pode atualizar o andamento da meta.
        $this->policy->authorizeEdit($user, (int) $row['athlete_id']);
        if (isset($data['status'])) {
            if ($data['status'] !== 'atingida') {
                $data['achieved_at'] = null;               // reaberta ou cancelada
            } elseif ($row['status'] !== 'atingida') {
                $data['achieved_at'] = date('Y-m-d');      // acabou de ser atingida
            }
        }
        $this->goals->update($id, $data);
        $this->audit->log($request, 'goal_updated', 'athlete', (int) $row['athlete_id'], ['goal_id' => $id] + array_intersect_key($data, ['status' => 1]));
    }

    public function deleteGoal(int $id, User $user, Request $request): void
    {
        $row = $this->goals->find($id) ?? throw new NotFoundException('Meta não encontrada.');
        $this->assertCanDelete($user, (int) $row['athlete_id'], $row['created_by']);
        $this->goals->delete($id);
        $this->audit->log($request, 'goal_deleted', 'athlete', (int) $row['athlete_id'], ['goal_id' => $id]);
    }

    private function assertCanDelete(User $user, int $athleteId, mixed $authorId): void
    {
        $this->policy->authorizeEdit($user, $athleteId);
        if (!$user->isAdmin() && (int) $authorId !== $user->id) {
            throw new ForbiddenException('Você só pode alterar registros feitos por você.');
        }
    }

    private function assertNotFuture(string $date, string $field): void
    {
        if ($date > date('Y-m-d')) {
            throw ValidationException::withField($field, 'A data não pode ser futura.');
        }
    }
}
