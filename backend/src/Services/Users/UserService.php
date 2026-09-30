<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Core\Session\SessionRevoker;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Security\PasswordHasherInterface;

/**
 * Gestão de usuários (somente admin).
 *
 * Regras de proteção:
 *  - ninguém desativa/rebaixa a si mesmo;
 *  - sempre resta ao menos um administrador ativo;
 *  - usuário novo e senha resetada recebem senha temporária (troca obrigatória);
 *  - desativar ou resetar senha encerra as sessões abertas do usuário.
 */
class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private SessionRevoker $revoker,
        private AuditLogger $audit,
        private ?PortalLinkValidator $links = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $links = $this->users->linkNames();

        return array_map(static fn (User $u) => $u->toArray() + ['link_name' => $links[$u->id] ?? null], $this->users->all());
    }

    /**
     * @param array{name: string, email: string, role: string, athlete_id?: ?int, guardian_id?: ?int} $data
     * @return array{user: array<string, mixed>, temporary_password: string}
     */
    public function create(array $data, Request $request): array
    {
        if ($this->users->emailExists($data['email'])) {
            throw ValidationException::withField('email', 'E-mail já cadastrado.');
        }
        $link = Role::from($data['role'])->isPortal()
            ? ($this->links ?? throw new \LogicException('Validação de vínculo indisponível.'))->resolve($data['role'], $data)
            : ['athlete_id' => null, 'guardian_id' => null];

        $password = self::temporaryPassword();
        $id = $this->users->create($link + [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password_hash' => $this->hasher->hash($password),
            'active' => true,
            'must_change_password' => true,
        ]);
        $this->audit->log($request, 'user_created', 'user', $id, ['role' => $data['role']] + array_filter($link));

        return ['user' => $this->get($id), 'temporary_password' => $password];
    }

    /** @param array{name?: string, email?: string, role?: string, active?: bool} $data */
    public function update(int $id, array $data, User $actor, Request $request): void
    {
        $user = $this->users->findById($id) ?? throw new NotFoundException('Usuário não encontrado.');

        if (isset($data['email']) && $this->users->emailExists($data['email'], $id)) {
            throw ValidationException::withField('email', 'E-mail já cadastrado.');
        }
        if (isset($data['role']) && $data['role'] !== $user->role->value
            && ($user->role->isPortal() || Role::from($data['role'])->isPortal())) {
            throw new ConflictException('Contas de atleta/responsável não podem mudar de perfil. Crie uma nova conta.');
        }
        if ($id === $actor->id) {
            if (isset($data['active']) && !$data['active']) {
                throw new ConflictException('Você não pode desativar o próprio usuário.');
            }
            if (isset($data['role']) && $data['role'] !== $user->role->value) {
                throw new ConflictException('Você não pode alterar o próprio perfil.');
            }
        }
        $losesAdmin = $user->isAdmin() && $user->active
            && ((isset($data['role']) && $data['role'] !== Role::ADMIN) || (isset($data['active']) && !$data['active']));
        if ($losesAdmin && $this->activeAdmins() <= 1) {
            throw new ConflictException('O sistema precisa de ao menos um administrador ativo.');
        }

        $this->users->update($id, $data);

        $changes = [];
        foreach (['name', 'email', 'role', 'active'] as $field) {
            if (array_key_exists($field, $data)) {
                $old = match ($field) {
                    'role' => $user->role->value,
                    'active' => $user->active,
                    default => $user->{$field},
                };
                if ($old !== $data[$field]) {
                    $changes[$field] = ['de' => $old, 'para' => $data[$field]];
                }
            }
        }

        // Perdeu acesso ou privilégio: derruba as sessões abertas.
        if ((isset($data['active']) && !$data['active']) || isset($changes['role'])) {
            $this->revoker->revokeUser($id);
        }
        $this->audit->log($request, 'user_updated', 'user', $id, $changes);
    }

    /** @return string nova senha temporária (exibida uma única vez) */
    public function resetPassword(int $id, User $actor, Request $request): string
    {
        $user = $this->users->findById($id) ?? throw new NotFoundException('Usuário não encontrado.');
        if ($user->id === $actor->id) {
            throw new ConflictException('Para trocar a sua senha use "Minha conta".');
        }
        $password = self::temporaryPassword();
        $this->users->updatePassword($id, $this->hasher->hash($password), true);
        $this->revoker->revokeUser($id);
        $this->audit->log($request, 'user_password_reset', 'user', $id);

        return $password;
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return ($this->users->findById($id) ?? throw new NotFoundException('Usuário não encontrado.'))->toArray();
    }

    /** Senha temporária legível (sem caracteres ambíguos), 12 caracteres com letras e números. */
    public static function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $all = $letters . $digits;
        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        for ($i = 0; $i < 10; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private function activeAdmins(): int
    {
        return count(array_filter($this->users->all(), static fn (User $u) => $u->isAdmin() && $u->active));
    }
}
