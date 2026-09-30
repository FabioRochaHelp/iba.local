<?php

declare(strict_types=1);

namespace App\Core\Routing;

final class Route
{
    /** @var list<string> */
    private array $paramNames = [];

    private string $regex;

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware  aliases (ex.: 'auth', 'csrf')
     * @param list<string> $roles       perfis autorizados; vazio + !public = negado
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $handler,
        public readonly array $middleware = [],
        public readonly array $roles = [],
        public readonly bool $public = false,
    ) {
        $this->regex = $this->compile($pattern);
    }

    /** @return array<string, string>|null */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($this->paramNames as $name) {
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_]+)(?::([^}]+))?\}#',
            function (array $m): string {
                $this->paramNames[] = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                if ($constraint === 'id') {
                    $constraint = '[1-9][0-9]{0,18}';
                }

                return '(?P<' . $m[1] . '>' . $constraint . ')';
            },
            rtrim($pattern, '/') ?: '/',
        );

        return '#^' . $regex . '$#';
    }
}
