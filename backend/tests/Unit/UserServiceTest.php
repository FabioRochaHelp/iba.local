<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ValidationException;
use App\Services\Audit\AuditLogger;
use App\Services\Users\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeSessionRevoker;
use Tests\Fakes\FastPasswordHasher;
use Tests\Fakes\InMemoryAuditLogRepository;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\RequestFactory;

final class UserServiceTest extends TestCase
{
    private InMemoryUserRepository $repo;
    private FakeSessionRevoker $revoker;
    private UserService $service;
    private FastPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->repo = new InMemoryUserRepository();
        $this->revoker = new FakeSessionRevoker();
        $this->hasher = new FastPasswordHasher();
        $this->service = new UserService($this->repo, $this->hasher, $this->revoker, new AuditLogger(new InMemoryAuditLogRepository()));
        $this->repo->create(['name' => 'Admin', 'email' => 'admin@x.com', 'password_hash' => 'x', 'role' => 'admin', 'must_change_password' => false]);
    }

    private function req(): \App\Core\Http\Request
    {
        return RequestFactory::make('POST', '/api/users');
    }

    public function testCreateGeneratesTemporaryPasswordAndForcesChange(): void
    {
        $result = $this->service->create(['name' => 'Prof', 'email' => 'prof@x.com', 'role' => 'professor'], $this->req());

        self::assertMatchesRegularExpression('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{12}$/', $result['temporary_password']);
        self::assertTrue($result['user']['must_change_password']);
        self::assertArrayNotHasKey('password_hash', $result['user']);
        self::assertTrue($this->hasher->verify($result['temporary_password'], $this->repo->rows[2]['password_hash']));
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create(['name' => 'Outro', 'email' => 'ADMIN@x.com', 'role' => 'professor'], $this->req());
    }

    public function testCannotDeactivateOrDemoteSelfNorLeaveNoAdmin(): void
    {
        $admin = $this->repo->findById(1);
        self::assertNotNull($admin);

        foreach ([['active' => false], ['role' => 'professor']] as $data) {
            try {
                $this->service->update(1, $data, $admin, $this->req());
                self::fail('Deveria impedir alteração do próprio usuário');
            } catch (ConflictException) {
            }
        }

        // Segundo admin tenta rebaixar o único outro admin ativo -> permitido só se sobrar um.
        $this->repo->create(['name' => 'Admin 2', 'email' => 'a2@x.com', 'password_hash' => 'x', 'role' => 'admin', 'must_change_password' => false]);
        $admin2 = $this->repo->findById(2);
        $this->service->update(1, ['role' => 'professor'], $admin2, $this->req());
        self::assertSame([1], $this->revoker->revoked, 'mudança de perfil derruba sessões');

        $this->repo->create(['name' => 'Prof', 'email' => 'p@x.com', 'password_hash' => 'x', 'role' => 'professor', 'must_change_password' => false]);
        $this->expectException(ConflictException::class);
        // Admin 2 agora é o único admin: não pode ser desativado por ninguém.
        $this->service->update(2, ['active' => false], $this->repo->findById(1), $this->req());
    }

    public function testResetPasswordRevokesSessions(): void
    {
        $created = $this->service->create(['name' => 'Prof', 'email' => 'prof@x.com', 'role' => 'professor'], $this->req());
        $id = $created['user']['id'];
        $this->repo->updatePassword($id, 'x', false);

        $new = $this->service->resetPassword($id, $this->repo->findById(1), $this->req());

        self::assertTrue($this->hasher->verify($new, $this->repo->rows[$id]['password_hash']));
        self::assertTrue($this->repo->rows[$id]['must_change_password']);
        self::assertContains($id, $this->revoker->revoked);

        $this->expectException(ConflictException::class);
        $this->service->resetPassword(1, $this->repo->findById(1), $this->req());
    }
}
