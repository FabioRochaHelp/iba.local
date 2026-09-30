<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\User;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Services\Classes\AttendanceService;
use App\Repositories\Contracts\SponsorshipRepositoryInterface;
use App\Repositories\Contracts\UniformRepositoryInterface;
use App\Services\Finance\DelinquencyService;
use App\Services\Finance\FinanceReportService;

/**
 * Indicadores do painel. Dados financeiros só para o admin.
 */
class DashboardService
{
    public function __construct(
        private AthleteRepositoryInterface $athletes,
        private FinanceReportService $reports,
        private DelinquencyService $delinquency,
        private UniformRepositoryInterface $uniforms,
        private SponsorshipRepositoryInterface $sponsorships,
        private ClassRepositoryInterface $classes,
        private AttendanceService $attendance,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(User $viewer): array
    {
        $month = (int) date('n');
        $data = [
            'athletes' => $this->athletes->counts(),
            'birthdays' => array_map(static fn (array $a) => [
                'id' => (int) $a['id'],
                'name' => $a['name'],
                'day' => (int) substr((string) $a['birth_date'], 8, 2),
                'turning' => (int) date('Y') - (int) substr((string) $a['birth_date'], 0, 4),
            ], $this->athletes->birthdaysInMonth($month)),
        ];

        $coachId = $viewer->isAdmin() ? null : $viewer->id;
        $data['attendance_rate_30d'] = $this->attendance->overallRate(30, $coachId);
        $data['classes_today'] = $this->classes->all(['active' => true, 'weekday' => (int) date('N'), 'coach_id' => $coachId]);

        if ($viewer->isAdmin()) {
            $delinquency = $this->delinquency->report();
            $data['finance'] = [
                'current' => $this->reports->monthly(date('Y-m')),
                'series' => $this->reports->series(6),
                'overdue_total' => $delinquency['total'],
                'overdue_guardians' => count($delinquency['groups']),
                'uniforms' => $this->uniforms->summary(),
                'sponsorships_year' => $this->sponsorships->totalBetween(date('Y') . '-01-01', date('Y') . '-12-31'),
            ];
        }

        return $data;
    }
}
