<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\Pdo\PdoUserRepository;

final class UserRepositoryIntegrationTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::$db?->execute("DELETE FROM users WHERE email LIKE 'it-user%@teste.local'");
    }

    public function testCreateFindAndMassAssignmentWhitelist(): void
    {
        $repo = new PdoUserRepository(self::$db);
        $id = $repo->create([
            'name' => 'Teste',
            'email' => 'IT-USER1@teste.local',
            'password_hash' => 'x',
            'role' => 'professor',
        ]);

        // Colunas fora da whitelist são ignoradas (password_hash não muda por update()).
        $repo->update($id, ['name' => 'Novo', 'password_hash' => 'hackeado']);
        $user = $repo->findByEmail('it-user1@teste.local');

        self::assertNotNull($user);
        self::assertSame('Novo', $user->name);
        self::assertSame('x', $user->passwordHash);
        self::assertNull($repo->findByEmail("it-user1@teste.local' OR '1'='1"));
    }
}
