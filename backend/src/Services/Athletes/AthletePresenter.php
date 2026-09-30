<?php

declare(strict_types=1);

namespace App\Services\Athletes;

use App\Models\User;
use App\Support\AgeCategory;
use App\Support\Masker;

/**
 * Monta a resposta conforme o perfil. Professor NÃO recebe dados
 * financeiros (valor do plano/desconto) nem CPF/e-mail do responsável.
 */
final class AthletePresenter
{
    /**
     * @param array<string, mixed> $athlete
     * @return array<string, mixed>
     */
    public function present(array $athlete, User $viewer, bool $detail = false): array
    {
        $athlete['age'] = AgeCategory::age($athlete['birth_date']);
        $athlete['category'] = AgeCategory::forBirthDate($athlete['birth_date']);

        if (!$viewer->isAdmin()) {
            if ($athlete['plan'] !== null) {
                $athlete['plan'] = [
                    'id' => $athlete['plan']['id'],
                    'name' => $athlete['plan']['name'],
                    'days_per_week' => $athlete['plan']['days_per_week'],
                ];
            }
            unset($athlete['guardian']['cpf'], $athlete['guardian']['email']);
        } elseif ($detail && isset($athlete['guardian']['cpf'])) {
            $athlete['guardian']['cpf_masked'] = Masker::cpf($athlete['guardian']['cpf']);
        }

        return $athlete;
    }
}
