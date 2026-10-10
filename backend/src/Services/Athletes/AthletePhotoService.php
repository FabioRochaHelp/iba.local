<?php

declare(strict_types=1);

namespace App\Services\Athletes;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AthletePhotoRepositoryInterface;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Evolution\AthleteAccessPolicy;

/**
 * Foto do atleta (dado pessoal de menor — LGPD): nunca fica em pasta pública.
 * Equipe vê a de qualquer atleta (a lista de atletas já é visível a ela);
 * portal só a dos atletas vinculados. Só o admin altera (garantido na rota).
 */
class AthletePhotoService
{
    public function __construct(
        private AthletePhotoRepositoryInterface $photos,
        private AthleteRepositoryInterface $athletes,
        private AthleteAccessPolicy $policy,
        private PhotoProcessor $processor,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{mime: string, data: string, version: string} */
    public function get(int $athleteId, User $viewer): array
    {
        $allowed = $viewer->isStaff() ? $this->athletes->exists($athleteId) : $this->policy->canView($viewer, $athleteId);
        if (!$allowed) {
            throw new NotFoundException();
        }

        return $this->photos->find($athleteId) ?? throw new NotFoundException('Atleta sem foto.');
    }

    public function save(int $athleteId, string $image, User $user, Request $request): string
    {
        $this->assertExists($athleteId);
        $version = $this->photos->save($athleteId, 'image/jpeg', $this->processor->process($image), $user->id);
        $this->audit->log($request, 'athlete_photo_updated', 'athlete', $athleteId);

        return $version;
    }

    public function delete(int $athleteId, Request $request): void
    {
        $this->assertExists($athleteId);
        if ($this->photos->delete($athleteId)) {
            $this->audit->log($request, 'athlete_photo_removed', 'athlete', $athleteId);
        }
    }

    private function assertExists(int $athleteId): void
    {
        if (!$this->athletes->exists($athleteId)) {
            throw new NotFoundException('Atleta não encontrado.');
        }
    }
}
