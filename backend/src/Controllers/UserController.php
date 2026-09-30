<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Models\Role;
use App\Services\Users\UserService;

final class UserController extends Controller
{
    public function __construct(Validator $validator, private UserService $users)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::data($this->users->list());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:3|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:' . implode(',', Role::values()),
            'athlete_id' => 'nullable|int|min:1',
            'guardian_id' => 'nullable|int|min:1',
        ]);

        return JsonResponse::data($this->users->create($data, $request), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, [
            'name' => 'sometimes|required|string|min:3|max:120',
            'email' => 'sometimes|required|email|max:190',
            'role' => 'sometimes|required|in:' . implode(',', Role::values()),
            'active' => 'sometimes|required|bool',
        ]);
        $this->users->update($id, $data, $this->user($request), $request);

        return JsonResponse::data($this->users->get($id));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $password = $this->users->resetPassword($this->id($request), $this->user($request), $request);

        return JsonResponse::data(['temporary_password' => $password]);
    }
}
