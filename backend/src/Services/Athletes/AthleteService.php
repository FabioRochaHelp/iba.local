<?php

declare(strict_types=1);

namespace App\Services\Athletes;

use App\Core\Database\Connection;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AthletePlanRepositoryInterface;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\GuardianRepositoryInterface;
use App\Repositories\Contracts\PositionRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Guardians\GuardianService;
use App\Services\Plans\PlanAssignmentService;

class AthleteService
{
    public function __construct(
        private AthleteRepositoryInterface $athletes,
        private GuardianRepositoryInterface $guardians,
        private PositionRepositoryInterface $positions,
        private AthletePlanRepositoryInterface $athletePlans,
        private GuardianService $guardianService,
        private PlanAssignmentService $planAssignment,
        private AthletePresenter $presenter,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters, int $page, int $perPage, User $viewer): array
    {
        if (!$viewer->isAdmin()) {
            unset($filters['plan_id']);
        }
        $result = $this->athletes->paginate($filters, $page, $perPage);
        $result['items'] = array_map(fn ($a) => $this->presenter->present($a, $viewer), $result['items']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function get(int $id, User $viewer): array
    {
        $athlete = $this->athletes->find($id) ?? throw new NotFoundException('Atleta não encontrado.');
        if ($viewer->isAdmin()) {
            $athlete['plan_history'] = $this->athletePlans->history($id);
        }

        return $this->presenter->present($athlete, $viewer, true);
    }

    /**
     * @param array<string, mixed> $data dados validados pelo controller
     */
    public function create(array $data, Request $request): int
    {
        return $this->db->transaction(function () use ($data, $request): int {
            $guardianId = $this->resolveGuardian($data, $request);

            $id = $this->athletes->create($this->athleteFields($data) + [
                'guardian_id' => $guardianId,
                'status' => $data['status'] ?? 'ativo',
                'enrollment_date' => $data['enrollment_date'] ?? date('Y-m-d'),
            ]);
            $this->athletes->syncPositions($id, $this->validPositions($data['position_ids'] ?? []));

            if (!empty($data['plan']['plan_id'])) {
                $this->planAssignment->assign($id, $data['plan'] + ['start_date' => $data['enrollment_date'] ?? date('Y-m-d')]);
            }

            $this->audit->log($request, 'athlete_created', 'athlete', $id);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        if (!$this->athletes->exists($id)) {
            throw new NotFoundException('Atleta não encontrado.');
        }

        $this->db->transaction(function () use ($id, $data, $request): void {
            $fields = $this->athleteFields($data);
            if (isset($data['guardian_id']) || isset($data['guardian'])) {
                $fields['guardian_id'] = $this->resolveGuardian($data, $request);
            }
            foreach (['status', 'enrollment_date'] as $key) {
                if (isset($data[$key])) {
                    $fields[$key] = $data[$key];
                }
            }
            $this->athletes->update($id, $fields);

            if (array_key_exists('position_ids', $data)) {
                $this->athletes->syncPositions($id, $this->validPositions($data['position_ids'] ?? []));
            }

            $planChanged = false;
            if (!empty($data['plan']['plan_id'])) {
                $planChanged = $this->planAssignment->assign($id, $data['plan']);
            }

            // Dados de saúde são sensíveis: auditamos só QUAIS campos mudaram.
            $this->audit->log($request, 'athlete_updated', 'athlete', $id, [
                'fields' => array_keys($fields),
                'plan_changed' => $planChanged,
            ]);
        });
    }

    public function delete(int $id, Request $request): void
    {
        if (!$this->athletes->exists($id)) {
            throw new NotFoundException('Atleta não encontrado.');
        }
        $this->athletes->softDelete($id);
        $this->audit->log($request, 'athlete_deleted', 'athlete', $id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function athleteFields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(['name', 'birth_date', 'notes']));

        if (array_key_exists('health_condition', $data) || array_key_exists('has_health_condition', $data)) {
            $condition = trim((string) ($data['health_condition'] ?? ''));
            $has = (bool) ($data['has_health_condition'] ?? ($condition !== ''));
            if ($has && $condition === '') {
                throw ValidationException::withField('health_condition', 'Descreva a condição de saúde.');
            }
            $fields['has_health_condition'] = $has;
            $fields['health_condition'] = $has ? $condition : null;
        }

        if (isset($fields['birth_date']) && $fields['birth_date'] > date('Y-m-d')) {
            throw ValidationException::withField('birth_date', 'Data de nascimento no futuro.');
        }

        return $fields;
    }

    /** @param array<string, mixed> $data */
    private function resolveGuardian(array $data, Request $request): int
    {
        if (!empty($data['guardian_id'])) {
            if ($this->guardians->find((int) $data['guardian_id']) === null) {
                throw ValidationException::withField('guardian_id', 'Responsável não encontrado.');
            }

            return (int) $data['guardian_id'];
        }
        if (!empty($data['guardian']) && is_array($data['guardian'])) {
            try {
                return $this->guardianService->create($data['guardian'], $request);
            } catch (ValidationException $e) {
                $fields = [];
                foreach ($e->errors() as $field => $message) {
                    $fields['guardian.' . $field] = $message;
                }
                throw new ValidationException($fields);
            }
        }

        throw ValidationException::withField('guardian_id', 'Selecione ou cadastre o responsável.');
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function validPositions(array $ids): array
    {
        $valid = $this->positions->existingIds($ids);
        if (count($valid) !== count(array_unique($ids))) {
            throw ValidationException::withField('position_ids', 'Posição inválida.');
        }

        return $valid;
    }
}
