<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Plans\PlanService;

final class PlanController extends Controller
{
    public function __construct(Validator $validator, private PlanService $plans)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::data($this->plans->list());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:3|max:80',
            'description' => 'nullable|string|max:255',
            'days_per_week' => 'required|int|min:1|max:7',
            'monthly_fee' => 'required|money',
        ]);

        return JsonResponse::data($this->plans->get($this->plans->create($data, $request)), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, [
            'name' => 'sometimes|required|string|min:3|max:80',
            'description' => 'sometimes|nullable|string|max:255',
            'days_per_week' => 'sometimes|required|int|min:1|max:7',
            'monthly_fee' => 'sometimes|required|money',
            'active' => 'sometimes|required|bool',
        ]);
        $this->plans->update($id, $data, $request);

        return JsonResponse::data($this->plans->get($id));
    }
}
