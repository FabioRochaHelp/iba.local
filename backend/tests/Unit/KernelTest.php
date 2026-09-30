<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container\Container;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Http\JsonResponse;
use App\Core\Http\Kernel;
use App\Core\Http\Request;
use App\Core\Routing\Router;
use App\Models\User;
use App\Models\Role;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\RequestFactory;

final class KernelTest extends TestCase
{
    public function ok(Request $r): JsonResponse
    {
        return JsonResponse::data('ok');
    }

    private function kernel(Router $router): Kernel
    {
        $c = new Container();
        $c->instance(self::class, $this);

        return new Kernel($c, $router, [], []);
    }

    public function testRouteWithoutRolesIsDeniedByDefault(): void
    {
        $r = new Router();
        $r->get('/api/esquecida', [self::class, 'ok']);

        $this->expectException(ForbiddenException::class);
        $this->kernel($r)->handle(RequestFactory::make('GET', '/api/esquecida'));
    }

    public function testPublicRouteIsAccessible(): void
    {
        $r = new Router();
        $r->get('/api/health', [self::class, 'ok'], ['public' => true]);

        self::assertSame(200, $this->kernel($r)->handle(RequestFactory::make('GET', '/api/health'))->status());
    }

    public function testRoleIsEnforced(): void
    {
        $r = new Router();
        $r->get('/api/invoices', [self::class, 'ok'], ['roles' => [Role::ADMIN]]);
        $kernel = $this->kernel($r);

        $anon = RequestFactory::make('GET', '/api/invoices');
        try {
            $kernel->handle($anon);
            self::fail('Esperava 401');
        } catch (UnauthorizedException) {
        }

        $prof = RequestFactory::make('GET', '/api/invoices');
        $prof->setAttribute('user', new User(2, 'Prof', 'p@x.com', Role::Professor, true, false));
        try {
            $kernel->handle($prof);
            self::fail('Esperava 403');
        } catch (ForbiddenException) {
        }

        $admin = RequestFactory::make('GET', '/api/invoices');
        $admin->setAttribute('user', new User(1, 'Adm', 'a@x.com', Role::Admin, true, false));
        self::assertSame(200, $kernel->handle($admin)->status());
    }
}
