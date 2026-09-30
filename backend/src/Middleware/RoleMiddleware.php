<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Models\User;

final class RoleMiddleware implements MiddlewareInterface
{
    /** @param list<string> $roles */
    public function __construct(private array $roles)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }
        if (!in_array($user->role->value, $this->roles, true)) {
            throw new ForbiddenException();
        }

        return $next($request);
    }
}
