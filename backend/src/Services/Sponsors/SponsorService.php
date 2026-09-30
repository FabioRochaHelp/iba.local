<?php

declare(strict_types=1);

namespace App\Services\Sponsors;

use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Models\User;
use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Contracts\SponsorRepositoryInterface;
use App\Repositories\Contracts\SponsorshipRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\PhoneNormalizer;
use DateTimeImmutable;

/**
 * Patrocinadores e entradas de patrocínio (gerais ou destinadas a um atleta).
 */
class SponsorService
{
    public function __construct(
        private SponsorRepositoryInterface $sponsors,
        private SponsorshipRepositoryInterface $sponsorships,
        private AthleteRepositoryInterface $athletes,
        private PhoneNormalizer $phones,
        private AuditLogger $audit,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function sponsors(?string $search = null): array
    {
        return $this->sponsors->all($search);
    }

    /** @param array<string, mixed> $data */
    public function saveSponsor(?int $id, array $data, Request $request): int
    {
        if ($id !== null && $this->sponsors->find($id) === null) {
            throw new NotFoundException('Patrocinador não encontrado.');
        }
        if (!empty($data['document'])) {
            $doc = (string) preg_replace('/\D/', '', (string) $data['document']);
            if (!in_array(strlen($doc), [11, 14], true)) {
                throw ValidationException::withField('document', 'Informe CPF (11 dígitos) ou CNPJ (14 dígitos).');
            }
            $data['document'] = $doc;
        }
        if (!empty($data['phone'])) {
            $data['phone'] = $this->phones->normalize((string) $data['phone'])
                ?? throw ValidationException::withField('phone', 'Telefone inválido.');
        }

        if ($id === null) {
            $id = $this->sponsors->create($data);
            $this->audit->log($request, 'sponsor_created', 'sponsor', $id);
        } else {
            $this->sponsors->update($id, $data);
            $this->audit->log($request, 'sponsor_updated', 'sponsor', $id, ['fields' => array_keys($data)]);
        }

        return $id;
    }

    public function deleteSponsor(int $id, Request $request): void
    {
        if ($this->sponsors->find($id) === null) {
            throw new NotFoundException('Patrocinador não encontrado.');
        }
        if ($this->sponsors->sponsorshipsCount($id) > 0) {
            throw new ConflictException('Há entradas lançadas para este patrocinador. Desative-o em vez de excluir.');
        }
        $this->sponsors->delete($id);
        $this->audit->log($request, 'sponsor_deleted', 'sponsor', $id);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, sum: string}
     */
    public function entries(array $filters, int $page, int $perPage): array
    {
        return $this->sponsorships->paginate($filters, $page, $perPage);
    }

    /** @param array<string, mixed> $data */
    public function addEntry(array $data, User $user, Request $request): int
    {
        if (empty($data['sponsor_id']) && empty($data['description'])) {
            throw ValidationException::withField('description', 'Sem patrocinador cadastrado, descreva a origem do valor.');
        }
        if (!empty($data['sponsor_id']) && $this->sponsors->find((int) $data['sponsor_id']) === null) {
            throw ValidationException::withField('sponsor_id', 'Patrocinador não encontrado.');
        }
        if (!empty($data['athlete_id']) && !$this->athletes->exists((int) $data['athlete_id'])) {
            throw ValidationException::withField('athlete_id', 'Atleta não encontrado.');
        }
        if ($data['received_at'] > date('Y-m-d')) {
            throw ValidationException::withField('received_at', 'A data não pode ser futura.');
        }
        if (Money::of((string) $data['amount'])->cents <= 0) {
            throw ValidationException::withField('amount', 'Informe um valor maior que zero.');
        }

        $id = $this->sponsorships->create($data + ['created_by' => $user->id]);
        $this->audit->log($request, 'sponsorship_created', 'sponsorship', $id, ['amount' => $data['amount']]);

        return $id;
    }

    public function deleteEntry(int $id, string $reason, Request $request): void
    {
        $entry = $this->sponsorships->find($id) ?? throw new NotFoundException('Lançamento não encontrado.');
        $this->sponsorships->delete($id);
        // Trilha completa do valor removido fica na auditoria.
        $this->audit->log($request, 'sponsorship_deleted', 'sponsorship', $id, [
            'reason' => $reason,
            'amount' => $entry['amount'],
            'sponsor' => $entry['sponsor_name'],
            'athlete' => $entry['athlete_name'],
            'received_at' => $entry['received_at'],
        ]);
    }

    /** @return array<string, mixed> */
    public function yearSummary(int $year): array
    {
        $from = "{$year}-01-01";
        $to = "{$year}-12-31";
        $byMonth = array_column($this->sponsorships->monthlyTotals($from, $to), 'total', 'month');
        $months = [];
        for ($d = new DateTimeImmutable($from); $d->format('Y') === (string) $year; $d = $d->modify('+1 month')) {
            $key = $d->format('Y-m');
            $months[] = ['month' => $key, 'total' => $byMonth[$key] ?? '0.00'];
        }

        return [
            'year' => $year,
            'total' => $this->sponsorships->totalBetween($from, $to),
            'by_month' => $months,
            'by_sponsor' => $this->sponsorships->totalsBySponsor($from, $to),
        ];
    }
}
