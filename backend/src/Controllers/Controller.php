<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Models\User;

/**
 * Base dos controllers: só ponte HTTP (validação de entrada + resposta).
 * Regra de negócio fica nos Services.
 */
abstract class Controller
{
    public function __construct(protected Validator $validator)
    {
    }

    /**
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules): array
    {
        return $this->validator->validate($request->body(), $rules);
    }

    /**
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    protected function validateQuery(Request $request, array $rules): array
    {
        return $this->validator->validate($request->allQuery(), $rules);
    }

    /**
     * Valida um objeto aninhado prefixando os erros ("guardian.name").
     *
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    protected function validateNested(mixed $value, array $rules, string $prefix): array
    {
        try {
            return $this->validator->validate(is_array($value) ? $value : [], $rules);
        } catch (ValidationException $e) {
            $fields = [];
            foreach ($e->errors() as $field => $message) {
                $fields["{$prefix}.{$field}"] = $message;
            }
            throw new ValidationException($fields);
        }
    }

    protected function user(Request $request): User
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        return $user;
    }

    protected function requireAdmin(Request $request): User
    {
        $user = $this->user($request);
        if (!$user->isAdmin()) {
            throw new ForbiddenException();
        }

        return $user;
    }

    protected function id(Request $request, string $param = 'id'): int
    {
        $value = $request->routeParam($param);
        if ($value === null || !ctype_digit($value) || (int) $value <= 0) {
            throw new NotFoundException();
        }

        return (int) $value;
    }

    /** @return array{page: int, per_page: int, search: ?string} */
    protected function pagination(Request $request, int $defaultPerPage = 20): array
    {
        $q = $this->validateQuery($request, [
            'page' => 'nullable|int|min:1|max:100000',
            'per_page' => 'nullable|int|min:1|max:100',
            'search' => 'nullable|string|max:100',
        ]);

        return [
            'page' => $q['page'] ?? 1,
            'per_page' => $q['per_page'] ?? $defaultPerPage,
            'search' => $q['search'] ?? null,
        ];
    }

    /** @param array{items: list<mixed>, total: int} $result */
    protected function paginated(array $result, int $page, int $perPage): JsonResponse
    {
        return JsonResponse::data($result['items'], 200, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
        ]);
    }
}
