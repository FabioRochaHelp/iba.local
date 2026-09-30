<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;

/**
 * Quem pode ver / registrar dados de um atleta (anti-IDOR):
 *
 *  - admin ........ vê e registra de qualquer atleta
 *  - professor .... vê e registra só de atletas das turmas ativas em que é responsável
 *  - atleta ....... vê somente a si mesmo (leitura)
 *  - responsável .. vê somente os próprios filhos (leitura)
 */
class AthleteAccessPolicy
{
    public function __construct(
        private AthleteRepositoryInterface $athletes,
        private ClassRepositoryInterface $classes,
    ) {
    }

    public function canView(User $user, int $athleteId): bool
    {
        $guardianId = $this->athletes->guardianIdOf($athleteId);
        if ($guardianId === null) {
            return false; // atleta inexistente ou excluído
        }

        return match ($user->role) {
            Role::Admin => true,
            Role::Professor => $this->classes->coachHasAthlete($user->id, $athleteId),
            // Portal: mesma regra da lista de atletas acessíveis (exclui inativos/excluídos).
            Role::Athlete, Role::Guardian => in_array($athleteId, array_column($this->portalAthletes($user), 'id'), true),
        };
    }

    public function canEdit(User $user, int $athleteId): bool
    {
        if (!$user->isStaff() || $this->athletes->guardianIdOf($athleteId) === null) {
            return false;
        }

        return $user->isAdmin() || $this->classes->coachHasAthlete($user->id, $athleteId);
    }

    public function authorizeView(User $user, int $athleteId): void
    {
        if (!$this->canView($user, $athleteId)) {
            // Para o portal, "não encontrado" não revela a existência de outros atletas.
            throw $user->isStaff() ? new ForbiddenException('Você não tem acesso a este atleta.') : new NotFoundException();
        }
    }

    public function authorizeEdit(User $user, int $athleteId): void
    {
        if (!$this->canEdit($user, $athleteId)) {
            throw new ForbiddenException('Somente o administrador ou o professor da turma do atleta pode registrar a evolução.');
        }
    }

    /**
     * Atletas acessíveis no portal.
     *
     * @return list<array{id: int, name: string, birth_date: ?string, status: string}>
     */
    public function portalAthletes(User $user): array
    {
        if ($user->role === Role::Athlete && $user->athleteId !== null) {
            $guardianId = $this->athletes->guardianIdOf($user->athleteId);
            if ($guardianId === null) {
                return [];
            }
            foreach ($this->athletes->byGuardian($guardianId) as $a) {
                if ($a['id'] === $user->athleteId) {
                    return [$a];
                }
            }

            return [];
        }
        if ($user->role === Role::Guardian && $user->guardianId !== null) {
            return $this->athletes->byGuardian($user->guardianId);
        }

        return [];
    }
}
