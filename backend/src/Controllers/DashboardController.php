<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Dashboard\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(Validator $validator, private DashboardService $dashboard)
    {
        parent::__construct($validator);
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->dashboard->summary($this->user($request)));
    }
}
