<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

final class AuditController extends Controller
{
    public function __construct(Validator $validator, private AuditLogRepositoryInterface $logs)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $p = $this->pagination($request, 50);
        $f = $this->validateQuery($request, [
            'entity' => 'nullable|string|max:60|regex:/^[a-z_]+$/',
            'action' => 'nullable|string|max:60|regex:/^[a-z_]+$/',
            'user_id' => 'nullable|int|min:1',
        ]);

        return $this->paginated($this->logs->paginate($f, $p['page'], $p['per_page']), $p['page'], $p['per_page']);
    }
}
