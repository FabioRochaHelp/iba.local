<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\PayloadTooLargeException;
use App\Core\Exceptions\UnsupportedMediaTypeException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use JsonException;

/**
 * Requisições de escrita só aceitam application/json (≤ 1 MB).
 * Isso também bloqueia CSRF via <form> HTML, que não consegue enviar JSON.
 */
final class JsonBodyGuardMiddleware implements MiddlewareInterface
{
    private const MAX_BYTES = 1048576;

    public function process(Request $request, callable $next): Response
    {
        if (!$request->isUnsafe()) {
            return $next($request);
        }

        $raw = $request->rawBody();
        if (strlen($raw) > self::MAX_BYTES || (int) $request->header('content-length', '0') > self::MAX_BYTES) {
            throw new PayloadTooLargeException();
        }

        $hasBody = trim($raw) !== '';
        $contentType = strtolower((string) $request->header('content-type', ''));
        // Sem corpo não há dado de formulário a forjar (e o token CSRF segue obrigatório);
        // o axios, aliás, remove o Content-Type em requisições sem corpo.
        if ($hasBody && !str_starts_with($contentType, 'application/json')) {
            throw new UnsupportedMediaTypeException();
        }

        if ($hasBody) {
            try {
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (JsonException) {
                throw new BadRequestException('JSON malformado.');
            }
            if (!is_array($data)) {
                throw new BadRequestException('O corpo da requisição deve ser um objeto JSON.');
            }
            $request->setParsedBody($data);
        }

        return $next($request);
    }
}
