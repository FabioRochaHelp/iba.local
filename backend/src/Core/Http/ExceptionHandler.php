<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Logging\LoggerInterface;
use Throwable;

/**
 * Converte exceções em respostas JSON padronizadas.
 * Em produção nunca expõe detalhes internos (stack trace, SQL, caminhos):
 * devolve um código de correlação que aponta para o log.
 */
final class ExceptionHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug,
    ) {
    }

    public function render(Throwable $e, ?Request $request = null): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return new JsonResponse(
                ['error' => ['message' => $e->getMessage(), 'fields' => $e->errors()]],
                422,
            );
        }

        if ($e instanceof HttpException) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], $e->status(), $e->headers());
        }

        $id = bin2hex(random_bytes(6));
        $this->logger->error($e->getMessage(), [
            'id' => $id,
            'type' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'method' => $request?->method(),
            'path' => $request?->path(),
            'user_id' => $request?->attribute('user')?->id ?? null,
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 15),
        ]);

        $error = ['message' => "Erro interno. Informe o código {$id} ao suporte.", 'id' => $id];
        if ($this->debug) {
            $error['debug'] = ['type' => $e::class, 'message' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()];
        }

        return new JsonResponse(['error' => $error], 500);
    }
}
