<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\AthletePlanRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Support\Money;
use DateTimeImmutable;

/**
 * Vincula um plano ao atleta preservando o histórico: o plano vigente
 * é encerrado na véspera e um novo registro começa na data informada.
 */
class PlanAssignmentService
{
    public const DISCOUNT_TYPES = ['nenhum', 'cortesia', 'percentual', 'valor'];

    public function __construct(
        private PlanRepositoryInterface $plans,
        private AthletePlanRepositoryInterface $athletePlans,
    ) {
    }

    /**
     * @param array{plan_id: int, discount_type?: ?string, discount_value?: ?string, start_date?: ?string} $data
     * @return bool true se houve mudança
     */
    public function assign(int $athleteId, array $data): bool
    {
        $plan = $this->plans->find((int) $data['plan_id']);
        if ($plan === null || !$plan['active']) {
            throw ValidationException::withField('plan.plan_id', 'Plano inválido ou inativo.');
        }

        $type = $data['discount_type'] ?? 'nenhum';
        $value = $type === 'nenhum' || $type === 'cortesia' ? '0.00' : (string) ($data['discount_value'] ?? '0.00');
        if ($type === 'percentual' && (float) $value > 100) {
            throw ValidationException::withField('plan.discount_value', 'Percentual máximo: 100.');
        }
        if ($type === 'valor' && (float) $value > (float) $plan['monthly_fee']) {
            throw ValidationException::withField('plan.discount_value', 'Desconto maior que a mensalidade.');
        }

        $current = $this->athletePlans->current($athleteId);
        if ($current !== null
            && (int) $current['plan_id'] === (int) $plan['id']
            && $current['discount_type'] === $type
            && Money::of((string) $current['discount_value'])->equals(Money::of($value))) {
            return false;
        }

        $start = $data['start_date'] ?? date('Y-m-d');
        if ($current !== null) {
            // Mudança no mesmo dia do início: substitui em vez de criar histórico de 1 dia.
            $this->athletePlans->deleteCurrentIfStartedOn($athleteId, $start);
            $yesterday = (new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
            $this->athletePlans->closeCurrent($athleteId, $yesterday);
        }

        $this->athletePlans->create([
            'athlete_id' => $athleteId,
            'plan_id' => (int) $plan['id'],
            'start_date' => $start,
            'discount_type' => $type,
            'discount_value' => $value,
        ]);

        return true;
    }
}
