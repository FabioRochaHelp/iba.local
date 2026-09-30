<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\TooManyRequestsException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Exceptions\ValidationException;
use App\Core\Security\CsrfTokenManager;
use App\Core\Session\ArraySession;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AuthService;
use App\Services\Security\LoginThrottle;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeSessionRevoker;
use Tests\Fakes\FastPasswordHasher;
use Tests\Fakes\InMemoryAuditLogRepository;
use Tests\Fakes\InMemoryLoginAttemptRepository;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\RequestFactory;

final class AuthServiceTest extends TestCase
{
    private InMemoryUserRepository $users;
    private ArraySession $session;
    private CsrfTokenManager $csrf;
    private InMemoryAuditLogRepository $audit;
    private FakeSessionRevoker $revoker;
    private AuthService $auth;
    private FastPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->session = new ArraySession();
        $this->csrf = new CsrfTokenManager($this->session);
        $this->audit = new InMemoryAuditLogRepository();
        $this->revoker = new FakeSessionRevoker();
        $this->hasher = new FastPasswordHasher();

        $this->auth = new AuthService(
            $this->users,
            $this->hasher,
            new LoginThrottle(new InMemoryLoginAttemptRepository()),
            $this->session,
            $this->csrf,
            $this->revoker,
            new AuditLogger($this->audit),
        );

        $this->users->create([
            'name' => 'Admin',
            'email' => 'admin@iba.com',
            'password_hash' => $this->hasher->hash('SenhaForte123'),
            'role' => 'admin',
            'must_change_password' => false,
        ]);
    }

    public function testSuccessfulLoginRegeneratesSessionAndCsrf(): void
    {
        $oldToken = $this->csrf->token();
        $before = $this->session->regenerations;

        $result = $this->auth->login('ADMIN@iba.com', 'SenhaForte123', RequestFactory::make('POST', '/api/auth/login'));

        self::assertSame(1, $result['user']->id);
        self::assertGreaterThan($before, $this->session->regenerations);
        self::assertNotSame($oldToken, $result['csrf_token']);
        self::assertSame(1, $this->session->get(AuthService::SESSION_USER_KEY));
        self::assertContains('login', $this->audit->actions());
    }

    public function testWrongPasswordAndUnknownEmailGiveSameGenericError(): void
    {
        $messages = [];
        foreach ([['admin@iba.com', 'errada'], ['naoexiste@iba.com', 'x']] as [$email, $pwd]) {
            try {
                $this->auth->login($email, $pwd, RequestFactory::make('POST', '/api/auth/login'));
            } catch (UnauthorizedException $e) {
                $messages[] = $e->getMessage();
            }
        }

        self::assertCount(2, $messages);
        self::assertSame($messages[0], $messages[1]);
        self::assertNull($this->session->get(AuthService::SESSION_USER_KEY));
    }

    public function testInactiveUserCannotLogin(): void
    {
        $this->users->update(1, ['active' => false]);

        $this->expectException(UnauthorizedException::class);
        $this->auth->login('admin@iba.com', 'SenhaForte123', RequestFactory::make('POST', '/api/auth/login'));
    }

    public function testSixthFailedAttemptIsLocked(): void
    {
        $req = RequestFactory::make('POST', '/api/auth/login');
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            try {
                $this->auth->login('admin@iba.com', 'errada' . $i, $req);
            } catch (UnauthorizedException) {
            }
        }

        $this->expectException(TooManyRequestsException::class);
        // Mesmo com a senha correta, está bloqueado.
        $this->auth->login('admin@iba.com', 'SenhaForte123', $req);
    }

    public function testChangePasswordRequiresCurrentAndRevokesOtherSessions(): void
    {
        $req = RequestFactory::make('PUT', '/api/auth/password');
        $user = $this->users->findById(1);
        self::assertNotNull($user);

        try {
            $this->auth->changePassword($user, 'errada', 'NovaSenha456', $req);
            self::fail('Esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors());
        }

        $this->auth->changePassword($user, 'SenhaForte123', 'NovaSenha456', $req);

        self::assertTrue($this->hasher->verify('NovaSenha456', $this->users->rows[1]['password_hash']));
        self::assertSame([1], $this->revoker->revoked);
    }

    public function testLogoutInvalidatesSession(): void
    {
        $this->auth->login('admin@iba.com', 'SenhaForte123', RequestFactory::make('POST', '/api/auth/login'));
        $this->auth->logout(RequestFactory::make('POST', '/api/auth/logout'));

        self::assertNull($this->auth->currentUser());
    }
}
