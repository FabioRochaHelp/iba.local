<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\User;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Services\Classes\AttendanceService;
use App\Services\Evolution\AthleteAccessPolicy;
use App\Services\Evolution\EvolutionService;
use App\Support\AgeCategory;
use DateTimeImmutable;

/**
 * Portal do atleta / responsável: somente leitura e somente os
 * atletas vinculados ao usuário.
 */
class PortalService
{
    public function __construct(
        private AthleteAccessPolicy $policy,
        private ClassRepositoryInterface $classes,
        private AttendanceService $attendance,
        private EvolutionService $evolution,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function athletes(User $user): array
    {
        return array_map(static fn (array $a) => $a + [
            'category' => AgeCategory::forBirthDate($a['birth_date']),
            'age' => AgeCategory::age($a['birth_date']),
        ], $this->policy->portalAthletes($user));
    }

    /** @return array<string, mixed> */
    public function overview(int $athleteId, User $user): array
    {
        $this->policy->authorizeView($user, $athleteId);
        $athlete = null;
        foreach ($this->athletes($user) as $a) {
            if ($a['id'] === $athleteId) {
                $athlete = $a;
            }
        }

        $evolution = $this->evolution->summary($athleteId, $user);
        unset($evolution['can_edit']);

        return [
            'athlete' => $athlete,
            'classes' => $this->classes->classesOfAthlete($athleteId),
            'attendance' => $this->attendance->athleteStats(
                $athleteId,
                (new DateTimeImmutable('-90 days'))->format('Y-m-d'),
                date('Y-m-d'),
            ),
            'evolution' => $evolution,
        ];
    }
}
