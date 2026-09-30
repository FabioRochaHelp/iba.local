<?php

declare(strict_types=1);

namespace App\Services\Classes;

use App\Core\Exceptions\ForbiddenException;
use App\Models\User;

/**
 * Autorização por recurso (anti-IDOR): professor só acessa as turmas
 * em que é o responsável; admin acessa todas.
 */
final class ClassAccessPolicy
{
    /** @param array<string, mixed> $class */
    public function canAccess(User $user, array $class): bool
    {
        return $user->isAdmin() || ($class['coach_id'] !== null && (int) $class['coach_id'] === $user->id);
    }

    /** @param array<string, mixed> $class */
    public function authorize(User $user, array $class): void
    {
        if (!$this->canAccess($user, $class)) {
            throw new ForbiddenException('Você não é o professor desta turma.');
        }
    }
}
