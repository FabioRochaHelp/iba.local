<?php

declare(strict_types=1);

namespace App\Services\Guardians;

use App\Core\Database\Connection;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Repositories\Contracts\GuardianRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\Masker;
use App\Support\PhoneNormalizer;

class GuardianService
{
    public function __construct(
        private GuardianRepositoryInterface $guardians,
        private PhoneNormalizer $phones,
        private Connection $db,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array{search?: ?string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $result = $this->guardians->paginate($filters, $page, $perPage);
        foreach ($result['items'] as &$item) {
            $item['cpf'] = Masker::cpf($item['cpf']); // LGPD: CPF mascarado na listagem
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->guardians->find($id) ?? throw new NotFoundException('Responsável não encontrado.');
    }

    /**
     * @param array<string, mixed> $data  name, cpf, email, notes, phones
     */
    public function create(array $data, ?Request $request = null): int
    {
        $clean = $this->prepare($data);

        return $this->db->transaction(function () use ($clean, $request): int {
            $id = $this->guardians->create($clean['guardian']);
            $this->guardians->syncPhones($id, $clean['phones']);
            $this->audit->log($request, 'guardian_created', 'guardian', $id);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?Request $request = null): void
    {
        $this->get($id);
        $clean = $this->prepare($data, $id);

        $this->db->transaction(function () use ($id, $clean, $request): void {
            $this->guardians->update($id, $clean['guardian']);
            $this->guardians->syncPhones($id, $clean['phones']);
            $this->audit->log($request, 'guardian_updated', 'guardian', $id, ['fields' => array_keys($clean['guardian'])]);
        });
    }

    public function delete(int $id, ?Request $request = null): void
    {
        $this->get($id);
        if ($this->guardians->athletesCount($id) > 0) {
            throw new ConflictException('Não é possível excluir: há atletas vinculados a este responsável.');
        }
        $this->guardians->delete($id);
        $this->audit->log($request, 'guardian_deleted', 'guardian', $id);
    }

    /**
     * Normaliza e valida telefones informados em formulário.
     *
     * @param list<array<string, mixed>> $phones
     * @return list<array{phone: string, is_whatsapp: bool, label: ?string}>
     */
    public function normalizePhones(array $phones, string $field = 'phones'): array
    {
        $result = [];
        $seen = [];
        foreach ($phones as $i => $phone) {
            $normalized = $this->phones->normalize((string) ($phone['phone'] ?? ''));
            if ($normalized === null) {
                throw ValidationException::withField("{$field}.{$i}", 'Telefone inválido. Informe DDD + número.');
            }
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $result[] = [
                'phone' => $normalized,
                'is_whatsapp' => (bool) ($phone['is_whatsapp'] ?? true),
                'label' => isset($phone['label']) && $phone['label'] !== '' ? mb_substr(strip_tags((string) $phone['label']), 0, 40) : null,
            ];
        }
        if ($result === []) {
            throw ValidationException::withField($field, 'Informe ao menos um telefone.');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{guardian: array<string, mixed>, phones: list<array{phone: string, is_whatsapp: bool, label: ?string}>}
     */
    private function prepare(array $data, ?int $id = null): array
    {
        $cpf = isset($data['cpf']) && $data['cpf'] !== null ? (string) preg_replace('/\D/', '', (string) $data['cpf']) : null;
        if ($cpf === '') {
            $cpf = null;
        }
        if ($cpf !== null) {
            if (!Masker::isValidCpf($cpf)) {
                throw ValidationException::withField('cpf', 'CPF inválido.');
            }
            if ($this->guardians->cpfExists($cpf, $id)) {
                throw ValidationException::withField('cpf', 'CPF já cadastrado para outro responsável.');
            }
        }

        return [
            'guardian' => [
                'name' => $data['name'],
                'cpf' => $cpf,
                'email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'phones' => $this->normalizePhones($data['phones'] ?? []),
        ];
    }
}
