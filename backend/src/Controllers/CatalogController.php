<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PositionRepositoryInterface;

/**
 * Listas auxiliares para formulários e filtros.
 */
final class CatalogController
{
    public function __construct(
        private PositionRepositoryInterface $positions,
        private PlanRepositoryInterface $plans,
    ) {
    }

    public function positions(Request $request): JsonResponse
    {
        return JsonResponse::data($this->positions->all());
    }

    /** Somente admin (contém valores). */
    public function plans(Request $request): JsonResponse
    {
        return JsonResponse::data($this->plans->all((bool) $request->query('active', false)));
    }
}
