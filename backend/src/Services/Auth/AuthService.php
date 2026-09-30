<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Exceptions\UnauthorizedException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\Request;
use App\Core\Security\CsrfTokenManager;
use App\Core\Session\SessionInterface;
use App\Core\Session\SessionRevoker;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Security\LoginThrottle;
use App\Services\Security\PasswordHasherInterface;

/**
 * Caso de uso de autenticação: login, logout e troca de senha.
 */
class AuthService
{
    public const SESSION_USER_KEY = '_uid';
    private const GENERIC_ERROR = 'E-mail ou senha inválidos.';

    /** Hash fictício para igualar o tempo de resposta quando o e-mail não existe. */
    private ?string $dummyHash = null;

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private LoginThrottle $throttle,
        private SessionInterface $session,
        private CsrfTokenManager $csrf,
        private SessionRevoker $revoker,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array{user: User, csrf_token: string}
     */
    public function login(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        $ip = $request->ip();

        $this->throttle->ensureNotLocked($email, $ip);

        $user = $this->users->findByEmail($email);
        $valid = $user !== null
            ? $this->hasher->verify($password, $user->passwordHash)
            : $this->hasher->verify($password, $this->dummyHash ??= $this->hasher->hash(bin2hex(random_bytes(8))));

        if (!$valid || $user === null || !$user->active) {
            $this->throttle->registerFailure($email, $ip);
            $this->audit->log($request, 'login_failed', 'user', $user?->id, ['email' => $email]);
            // Mensagem genérica: não revela se o e-mail existe ou está inativo.
            throw new UnauthorizedException(self::GENERIC_ERROR);
        }

        $this->throttle->registerSuccess($email, $ip);

        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->updatePassword($user->id, $this->hasher->hash($password), $user->mustChangePassword);
        }

        // Novo ID de sessão no login (anti session fixation) + novo token CSRF.
        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_KEY, $user->id);
        $token = $this->csrf->regenerate();

        $this->users->touchLastLogin($user->id);
        $this->audit->log($request, 'login', 'user', $user->id, [], $user->id);

        return ['user' => $user, 'csrf_token' => $token];
    }

    public function logout(Request $request): string
    {
        $user = $request->attribute('user');
        if ($user instanceof User) {
            $this->audit->log($request, 'logout', 'user', $user->id);
        }
        $this->session->invalidate();

        return $this->csrf->regenerate();
    }

    public function changePassword(User $user, string $current, string $new, Request $request): string
    {
        if (!$this->hasher->verify($current, $user->passwordHash)) {
            throw ValidationException::withField('current_password', 'Senha atual incorreta.');
        }
        if (hash_equals($current, $new)) {
            throw ValidationException::withField('new_password', 'A nova senha deve ser diferente da atual.');
        }
        if (stripos($new, explode('@', $user->email)[0]) !== false) {
            throw ValidationException::withField('new_password', 'A senha não pode conter seu e-mail.');
        }

        $this->users->updatePassword($user->id, $this->hasher->hash($new), false);
        $this->session->regenerate();
        $this->revoker->revokeUser($user->id, $this->session->id());
        $this->audit->log($request, 'password_changed', 'user', $user->id);

        return $this->csrf->regenerate();
    }

    public function currentUser(): ?User
    {
        $id = $this->session->get(self::SESSION_USER_KEY);
        if (!is_int($id) || $id <= 0) {
            return null;
        }
        $user = $this->users->findById($id);

        return $user !== null && $user->active ? $user : null;
    }
}
