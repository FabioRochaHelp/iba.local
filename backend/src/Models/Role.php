<?php

declare(strict_types=1);

namespace App\Models;

enum Role: string
{
    case Admin = 'admin';
    case Professor = 'professor';
    case Athlete = 'atleta';
    case Guardian = 'responsavel';

    public const ADMIN = 'admin';
    public const PROFESSOR = 'professor';
    public const ATHLETE = 'atleta';
    public const GUARDIAN = 'responsavel';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $r) => $r->value, self::cases());
    }

    /** Perfis da equipe (acessam a área administrativa). */
    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Professor;
    }

    /** Perfis do portal (acesso somente leitura aos próprios dados). */
    public function isPortal(): bool
    {
        return !$this->isStaff();
    }
}
