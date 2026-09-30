<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Exceptions\MethodNotAllowedException;
use App\Core\Exceptions\NotFoundException;
use Closure;

/**
 * Roteador com grupos (prefixo, middlewares e perfis herdados).
 *
 * Política "negar por padrão": uma rota só é acessível se for marcada
 * como pública ou declarar explicitamente os perfis autorizados.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string>, roles: list<string>, public: bool}> */
    private array $groupStack = [];

    /**
     * @param array{prefix?: string, middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function group(array $options, Closure $routes): void
    {
        $parent = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'roles' => [], 'public' => false];

        $this->groupStack[] = [
            'prefix' => $parent['prefix'] . '/' . trim($options['prefix'] ?? '', '/'),
            'middleware' => array_values(array_merge($parent['middleware'], $options['middleware'] ?? [])),
            'roles' => $options['roles'] ?? $parent['roles'],
            'public' => $options['public'] ?? $parent['public'],
        ];

        $routes($this);
        array_pop($this->groupStack);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function add(string $method, string $pattern, array $handler, array $options = []): void
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'roles' => [], 'public' => false];
        $path = preg_replace('#/+#', '/', $group['prefix'] . '/' . trim($pattern, '/'));

        $this->routes[] = new Route(
            strtoupper($method),
            rtrim((string) $path, '/') ?: '/',
            $handler,
            array_values(array_merge($group['middleware'], $options['middleware'] ?? [])),
            $options['roles'] ?? $group['roles'],
            $options['public'] ?? $group['public'],
        );
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function get(string $pattern, array $handler, array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function post(string $pattern, array $handler, array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function put(string $pattern, array $handler, array $options = []): void
    {
        $this->add('PUT', $pattern, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function patch(string $pattern, array $handler, array $options = []): void
    {
        $this->add('PATCH', $pattern, $handler, $options);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{middleware?: list<string>, roles?: list<string>, public?: bool} $options
     */
    public function delete(string $pattern, array $handler, array $options = []): void
    {
        $this->add('DELETE', $pattern, $handler, $options);
    }

    public function match(string $method, string $path): RouteMatch
    {
        $path = rtrim($path, '/') ?: '/';
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }
            if ($route->method === $method) {
                return new RouteMatch($route, $params);
            }
            $allowed[] = $route->method;
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException();
        }

        throw new NotFoundException('Rota não encontrada.');
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
