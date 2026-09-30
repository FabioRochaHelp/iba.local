<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Portal\PortalService;

/** Portal do atleta/responsável — somente leitura dos próprios dados. */
final class PortalController extends Controller
{
    public function __construct(Validator $validator, private PortalService $portal)
    {
        parent::__construct($validator);
    }

    public function athletes(Request $request): JsonResponse
    {
        return JsonResponse::data($this->portal->athletes($this->user($request)));
    }

    public function overview(Request $request): JsonResponse
    {
        return JsonResponse::data($this->portal->overview($this->id($request), $this->user($request)));
    }
}
