<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;

final class HealthController
{
    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data(['status' => 'ok', 'time' => date(DATE_ATOM)]);
    }
}
