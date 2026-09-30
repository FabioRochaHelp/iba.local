<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Services\Audit\AuditLogger;

/**
 * Cadastro de planos. Alterar o valor afeta só as mensalidades geradas
 * dali em diante — as já geradas guardam o valor da época.
 */
class PlanService
{
    public function __construct(
        private PlanRepositoryInterface $plans,
        private AuditLogger $audit,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->plans->all();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        if ($this->plans->nameExists($data['name'])) {
            throw ValidationException::withField('name', 'Já existe um plano com este nome.');
        }
        $id = $this->plans->create($data + ['active' => true]);
        $this->audit->log($request, 'plan_created', 'plan', $id, ['monthly_fee' => $data['monthly_fee']]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        $plan = $this->plans->find($id) ?? throw new NotFoundException('Plano não encontrado.');
        if (isset($data['name']) && $this->plans->nameExists($data['name'], $id)) {
            throw ValidationException::withField('name', 'Já existe um plano com este nome.');
        }
        $this->plans->update($id, $data);

        $changes = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $plan) && (string) $plan[$key] !== (string) $value) {
                $changes[$key] = ['de' => $plan[$key], 'para' => $value];
            }
        }
        $this->audit->log($request, 'plan_updated', 'plan', $id, $changes);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $plan = $this->plans->find($id) ?? throw new NotFoundException('Plano não encontrado.');
        $plan['athletes_count'] = $this->plans->activeAthletesCount($id);

        return $plan;
    }
}
