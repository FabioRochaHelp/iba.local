<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config\Config;
use App\Core\Exceptions\CsrfException;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Security\CsrfTokenManager;

/**
 * Proteção CSRF em toda requisição de escrita (inclusive o login):
 *  1. Origin/Referer devem ser do próprio domínio;
 *  2. header X-CSRF-Token deve bater com o token da sessão (hash_equals).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CsrfTokenManager $csrf,
        private Config $config,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$request->isUnsafe()) {
            return $next($request);
        }

        $this->assertSameOrigin($request);

        if (!$this->csrf->isValid($request->header('x-csrf-token'))) {
            throw new CsrfException();
        }

        return $next($request);
    }

    private function assertSameOrigin(Request $request): void
    {
        $source = $request->header('origin');
        if ($source === null || $source === '' || $source === 'null') {
            $referer = $request->header('referer');
            if ($referer === null || $referer === '') {
                return; // cliente sem Origin/Referer (ex.: CLI): o token CSRF continua obrigatório
            }
            $source = $referer;
        }

        $origin = $this->originOf($source);
        foreach ((array) $this->config->get('app.allowed_origins', []) as $allowed) {
            if ($origin !== null && $origin === $this->originOf((string) $allowed)) {
                return;
            }
        }

        throw new ForbiddenException('Origem da requisição não permitida.');
    }

    private function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($parts['scheme'] . '://' . $parts['host'] . $port);
    }
}
