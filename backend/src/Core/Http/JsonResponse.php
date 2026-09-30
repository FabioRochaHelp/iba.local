<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Resposta JSON. Os flags JSON_HEX_* escapam < > & ' " e impedem que
 * o JSON seja interpretado como HTML (defesa em profundidade contra XSS).
 */
final class JsonResponse extends Response
{
    public const FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS
        | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** @param array<string, string> $headers */
    public function __construct(mixed $payload, int $status = 200, array $headers = [])
    {
        parent::__construct(
            $status === 204 ? '' : json_encode($payload, self::FLAGS),
            $status,
            $headers + [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /** @param array<string, mixed> $meta */
    public static function data(mixed $data, int $status = 200, array $meta = []): self
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new self($payload, $status);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }
}
