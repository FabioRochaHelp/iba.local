<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Sponsors\SponsorService;

final class SponsorController extends Controller
{
    private const RULES = [
        'name' => 'required|string|min:2|max:120',
        'document' => 'sometimes|nullable|string|max:20',
        'contact' => 'sometimes|nullable|string|max:120',
        'phone' => 'sometimes|nullable|string|max:30',
        'email' => 'sometimes|nullable|email|max:190',
        'notes' => 'sometimes|nullable|text|max:2000',
        'active' => 'sometimes|required|bool',
    ];

    public function __construct(Validator $validator, private SponsorService $sponsors)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->validateQuery($request, ['search' => 'nullable|string|max:100']);

        return JsonResponse::data($this->sponsors->sponsors($q['search'] ?? null));
    }

    public function store(Request $request): JsonResponse
    {
        return JsonResponse::data(['id' => $this->sponsors->saveSponsor(null, $this->validate($request, self::RULES), $request)], 201);
    }

    public function update(Request $request): JsonResponse
    {
        return JsonResponse::data(['id' => $this->sponsors->saveSponsor($this->id($request), $this->validate($request, self::RULES), $request)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->sponsors->deleteSponsor($this->id($request), $request);

        return JsonResponse::noContent();
    }

    public function entries(Request $request): JsonResponse
    {
        $p = $this->pagination($request, 50);
        $f = $this->validateQuery($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'sponsor_id' => 'nullable|int|min:1',
            'athlete_id' => 'nullable|int|min:1',
        ]);
        $result = $this->sponsors->entries($f + ['search' => $p['search']], $p['page'], $p['per_page']);

        return JsonResponse::data($result['items'], 200, [
            'page' => $p['page'],
            'per_page' => $p['per_page'],
            'total' => $result['total'],
            'sum' => $result['sum'],
        ]);
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'sponsor_id' => 'nullable|int|min:1',
            'athlete_id' => 'nullable|int|min:1',
            'amount' => 'required|money',
            'received_at' => 'required|date',
            'method' => 'required|in:pix,dinheiro,cartao,transferencia,produto',
            'description' => 'nullable|string|max:255',
        ]);

        return JsonResponse::data(['id' => $this->sponsors->addEntry($data, $this->user($request), $request)], 201);
    }

    public function destroyEntry(Request $request): JsonResponse
    {
        $data = $this->validate($request, ['reason' => 'required|string|min:3|max:255']);
        $this->sponsors->deleteEntry($this->id($request), $data['reason'], $request);

        return JsonResponse::noContent();
    }

    public function summary(Request $request): JsonResponse
    {
        $q = $this->validateQuery($request, ['year' => 'nullable|int|min:2000|max:2100']);

        return JsonResponse::data($this->sponsors->yearSummary($q['year'] ?? (int) date('Y')));
    }
}
