<?php

declare(strict_types=1);

namespace App\Services\Classes;

use App\Core\Database\Connection;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\AgeCategory;

class ClassService
{
    public const WEEKDAYS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

    public function __construct(
        private ClassRepositoryInterface $classes,
        private AthleteRepositoryInterface $athletes,
        private UserRepositoryInterface $users,
        private ClassAccessPolicy $policy,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array{active?: ?bool, weekday?: ?int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(User $viewer, array $filters = []): array
    {
        if (!$viewer->isAdmin()) {
            $filters['coach_id'] = $viewer->id; // professor vê só as próprias turmas
        }

        return $this->classes->all($filters);
    }

    /** @return array<string, mixed> */
    public function get(int $id, User $viewer): array
    {
        $class = $this->classes->find($id) ?? throw new NotFoundException('Turma não encontrada.');
        $this->policy->authorize($viewer, $class);
        $class['athletes'] = array_map(static fn (array $a) => $a + [
            'category' => AgeCategory::forBirthDate($a['birth_date']),
        ], $this->classes->roster($id));

        return $class;
    }

    /** @param array<string, mixed> $data */
    public function save(?int $id, array $data, Request $request): int
    {
        if ($id !== null && $this->classes->find($id) === null) {
            throw new NotFoundException('Turma não encontrada.');
        }
        if (isset($data['start_time'], $data['end_time']) && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withField('end_time', 'O término deve ser depois do início.');
        }
        if (isset($data['min_birth_year'], $data['max_birth_year']) && $data['min_birth_year'] > $data['max_birth_year']) {
            throw ValidationException::withField('max_birth_year', 'Ano final menor que o inicial.');
        }
        if (!empty($data['coach_id'])) {
            $coach = $this->users->findById((int) $data['coach_id']);
            if ($coach === null || !$coach->active) {
                throw ValidationException::withField('coach_id', 'Professor inválido ou inativo.');
            }
        }

        if ($id === null) {
            $id = $this->classes->create($data + ['active' => true]);
            $this->audit->log($request, 'class_created', 'class', $id);
        } else {
            $this->classes->update($id, $data);
            $this->audit->log($request, 'class_updated', 'class', $id, ['fields' => array_keys($data)]);
        }

        return $id;
    }

    /** @param list<int> $athleteIds */
    public function setAthletes(int $classId, array $athleteIds, Request $request): void
    {
        if ($this->classes->find($classId) === null) {
            throw new NotFoundException('Turma não encontrada.');
        }
        foreach ($athleteIds as $athleteId) {
            if (!$this->athletes->exists($athleteId)) {
                throw ValidationException::withField('athlete_ids', "Atleta {$athleteId} não encontrado.");
            }
        }
        $this->db->transaction(fn () => $this->classes->syncAthletes($classId, $athleteIds));
        $this->audit->log($request, 'class_athletes_updated', 'class', $classId, ['count' => count($athleteIds)]);
    }

    /** @return list<array<string, mixed>> */
    public function suggestions(int $classId): array
    {
        $class = $this->classes->find($classId) ?? throw new NotFoundException('Turma não encontrada.');

        return array_map(static fn (array $a) => $a + ['category' => AgeCategory::forBirthDate($a['birth_date'])],
            $this->classes->suggestions($classId, $class['min_birth_year'], $class['max_birth_year']));
    }

    /** @return list<array{id: int, name: string, role: string}> */
    public function coaches(): array
    {
        $result = [];
        foreach ($this->users->all() as $user) {
            if ($user->active) {
                $result[] = ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value];
            }
        }

        return $result;
    }
}
