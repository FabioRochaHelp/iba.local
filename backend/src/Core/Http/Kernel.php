<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Container\Container;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Middleware\Pipeline;
use App\Core\Routing\Router;
use App\Middleware\RoleMiddleware;

/**
 * Núcleo HTTP: middlewares globais → roteamento → middlewares da rota
 * → verificação de perfil → controller.
 */
final class Kernel
{
    /**
     * @param list<class-string<MiddlewareInterface>>                $globalMiddleware
     * @param array<string, class-string<MiddlewareInterface>>        $aliases
     */
    public function __construct(
        private Container $container,
        private Router $router,
        private array $globalMiddleware,
        private array $aliases,
    ) {
    }

    public function handle(Request $request): Response
    {
        $pipeline = new Pipeline(array_map(fn (string $class) => $this->container->get($class), $this->globalMiddleware));

        return $pipeline->handle($request, fn (Request $req): Response => $this->dispatch($req));
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());
        $route = $match->route;
        $request->setAttribute('route_params', $match->params);
        $request->setAttribute('route', $route);

        $middlewares = [];
        foreach ($route->middleware as $alias) {
            $class = $this->aliases[$alias] ?? throw new \LogicException("Middleware '{$alias}' não registrado.");
            $middlewares[] = $this->container->get($class);
        }

        if (!$route->public) {
            // Negar por padrão: rota protegida sem perfis declarados não é acessível.
            if ($route->roles === []) {
                throw new ForbiddenException();
            }
            $middlewares[] = new RoleMiddleware($route->roles);
        }

        [$class, $method] = $route->handler;

        return (new Pipeline($middlewares))->handle(
            $request,
            fn (Request $req): Response => $this->container->get($class)->{$method}($req),
        );
    }
}
