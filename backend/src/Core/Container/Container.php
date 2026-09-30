<?php

declare(strict_types=1);

namespace App\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Container de injeção de dependência com autowiring por Reflection.
 * É aqui (via config/container.php) que interfaces são ligadas às
 * implementações concretas — Princípio da Inversão de Dependência.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    public function bind(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, false);
    }

    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, true);
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : object)
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException("Dependência circular ao resolver {$id}.");
        }
        $this->resolving[$id] = true;

        try {
            $object = isset($this->factories[$id])
                ? ($this->factories[$id])($this)
                : $this->build($id);
        } finally {
            unset($this->resolving[$id]);
        }

        if (!empty($this->shared[$id])) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    private function register(string $id, Closure|string|null $concrete, bool $shared): void
    {
        $concrete ??= $id;
        $this->factories[$id] = $concrete instanceof Closure
            ? $concrete
            : fn (Container $c) => $c->build($concrete);
        $this->shared[$id] = $shared;
        unset($this->instances[$id]);
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Não foi possível resolver '{$class}': classe inexistente ou interface sem binding.");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("'{$class}' não é instanciável. Registre um binding em config/container.php.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new ContainerException(
                "Parâmetro \${$param->getName()} de {$class} não pode ser resolvido automaticamente."
            );
        }

        return $reflection->newInstanceArgs($args);
    }
}
