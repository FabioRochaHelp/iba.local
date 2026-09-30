<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container\Container;
use App\Repositories\Contracts\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;

final class ContainerTest extends TestCase
{
    public function testAutowiresAndBindsInterfaces(): void
    {
        $c = new Container();
        $c->singleton(UserRepositoryInterface::class, InMemoryUserRepository::class);

        $a = $c->get(UserRepositoryInterface::class);
        $b = $c->get(UserRepositoryInterface::class);

        self::assertInstanceOf(InMemoryUserRepository::class, $a);
        self::assertSame($a, $b);
    }
}
