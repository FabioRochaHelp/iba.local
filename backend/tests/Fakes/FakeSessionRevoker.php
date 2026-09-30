<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Core\Session\SessionRevoker;

final class FakeSessionRevoker extends SessionRevoker
{
    /** @var list<int> */
    public array $revoked = [];

    public function __construct()
    {
    }

    public function revokeUser(int $userId, ?string $exceptSessionId = null): void
    {
        $this->revoked[] = $userId;
    }
}
