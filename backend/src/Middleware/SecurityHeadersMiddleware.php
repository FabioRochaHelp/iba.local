<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;

/**
 * Cabeçalhos HTTP de segurança em todas as respostas da API.
 * (O .htaccess aplica os mesmos cabeçalhos aos arquivos da SPA.)
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            // A API só devolve JSON: nada deve ser carregado/executado a partir dela.
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        ];
        if ($this->config->get('session.secure')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
