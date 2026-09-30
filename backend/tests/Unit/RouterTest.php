<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\MethodNotAllowedException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testGroupsInheritPrefixMiddlewareAndRoles(): void
    {
        $r = new Router();
        $r->group(['prefix' => '/api', 'middleware' => ['auth'], 'roles' => ['admin']], function (Router $r): void {
            $r->get('/athletes/{id:id}', [self::class, 'x']);
        });

        $match = $r->match('GET', '/api/athletes/42');

        self::assertSame(['id' => '42'], $match->params);
        self::assertSame(['auth'], $match->route->middleware);
        self::assertSame(['admin'], $match->route->roles);
        self::assertFalse($match->route->public);
    }

    public function testIdConstraintRejectsNonNumeric(): void
    {
        $r = new Router();
        $r->get('/api/athletes/{id:id}', [self::class, 'x']);

        $this->expectException(NotFoundException::class);
        $r->match('GET', "/api/athletes/1 OR 1=1");
    }

    public function testMethodNotAllowed(): void
    {
        $r = new Router();
        $r->get('/api/x', [self::class, 'x']);

        $this->expectException(MethodNotAllowedException::class);
        $r->match('DELETE', '/api/x');
    }
}
