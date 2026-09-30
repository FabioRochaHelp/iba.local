<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Security\CsrfTokenManager;
use App\Core\Validation\Validator;
use App\Services\Auth\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        Validator $validator,
        private AuthService $auth,
        private CsrfTokenManager $csrf,
    ) {
        parent::__construct($validator);
    }

    /** GET /api/auth/csrf — entrega o token CSRF da sessão atual. */
    public function csrf(Request $request): JsonResponse
    {
        return JsonResponse::data([
            'csrf_token' => $this->csrf->token(),
            'user' => $this->auth->currentUser()?->toArray(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:190',
            'password' => 'required|raw|max:200',
        ]);

        $result = $this->auth->login($data['email'], $data['password'], $request);

        return JsonResponse::data([
            'user' => $result['user']->toArray(),
            'csrf_token' => $result['csrf_token'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return JsonResponse::data($this->user($request)->toArray());
    }

    public function logout(Request $request): JsonResponse
    {
        return JsonResponse::data(['csrf_token' => $this->auth->logout($request)]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'current_password' => 'required|raw|max:200',
            'new_password' => 'required|raw|password|max:200',
        ]);

        $token = $this->auth->changePassword($this->user($request), $data['current_password'], $data['new_password'], $request);

        return JsonResponse::data(['csrf_token' => $token]);
    }
}
