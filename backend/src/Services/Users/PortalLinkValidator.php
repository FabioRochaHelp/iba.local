<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Core\Exceptions\ValidationException;
use App\Models\Role;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\GuardianRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Valida o vínculo de contas do portal: atleta → um atleta existente,
 * responsável → um responsável existente; um vínculo = uma conta.
 */
class PortalLinkValidator
{
    public function __construct(
        private AthleteRepositoryInterface $athletes,
        private GuardianRepositoryInterface $guardians,
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{athlete_id: ?int, guardian_id: ?int}
     */
    public function resolve(string $role, array $data): array
    {
        if ($role === Role::ATHLETE) {
            $athleteId = (int) ($data['athlete_id'] ?? 0);
            if ($athleteId <= 0 || $this->athletes->guardianIdOf($athleteId) === null) {
                throw ValidationException::withField('athlete_id', 'Selecione o atleta desta conta.');
            }
            if ($this->users->findByAthlete($athleteId) !== null) {
                throw ValidationException::withField('athlete_id', 'Este atleta já possui uma conta de acesso.');
            }

            return ['athlete_id' => $athleteId, 'guardian_id' => null];
        }

        if ($role === Role::GUARDIAN) {
            $guardianId = (int) ($data['guardian_id'] ?? 0);
            if ($guardianId <= 0 || $this->guardians->find($guardianId) === null) {
                throw ValidationException::withField('guardian_id', 'Selecione o responsável desta conta.');
            }
            if ($this->users->findByGuardian($guardianId) !== null) {
                throw ValidationException::withField('guardian_id', 'Este responsável já possui uma conta de acesso.');
            }

            return ['athlete_id' => null, 'guardian_id' => $guardianId];
        }

        return ['athlete_id' => null, 'guardian_id' => null];
    }
}
