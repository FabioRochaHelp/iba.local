<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Services\Auth\AuthService;

/**
 * Exige usuário autenticado e ativo. Usuário com senha temporária só
 * acessa as rotas de troca de senha / logout / me.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private const ALLOWED_WITH_TEMP_PASSWORD = ['/api/auth/me', '/api/auth/password', '/api/auth/logout', '/api/auth/csrf'];

    public function __construct(private AuthService $auth)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $this->auth->currentUser();
        if ($user === null) {
            throw new UnauthorizedException();
        }

        if ($user->mustChangePassword && !in_array($request->path(), self::ALLOWED_WITH_TEMP_PASSWORD, true)) {
            throw new ForbiddenException('Troque sua senha temporária para continuar.');
        }

        $request->setAttribute('user', $user);

        return $next($request);
    }
}
