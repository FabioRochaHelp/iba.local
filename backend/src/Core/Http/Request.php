<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Representação imutável (na prática) da requisição HTTP.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, mixed>|null */
    private ?array $parsedBody = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers  chaves em minúsculas
     * @param array<string, mixed>  $server
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $headers,
        private string $rawBody,
        private array $server = [],
    ) {
        $this->method = strtoupper($method);
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rawurldecode($path),
            $_GET,
            $headers,
            (string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024),
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        $path = '/' . trim($this->path, '/');

        return $path;
    }

    public function isUnsafe(): bool
    {
        return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** @param array<string, mixed> $body */
    public function setParsedBody(array $body): void
    {
        $this->parsedBody = $body;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->parsedBody ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $default;
    }

    public function ip(): string
    {
        // Não confiamos em X-Forwarded-For (pode ser forjado pelo cliente).
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('user-agent', ''), 0, 255);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function routeParam(string $key): ?string
    {
        $params = $this->attributes['route_params'] ?? [];

        return isset($params[$key]) ? (string) $params[$key] : null;
    }
}
