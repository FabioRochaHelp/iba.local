<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Athletes\AthleteService;

final class AthleteController extends Controller
{
    private const PHONE_RULES = [
        'phone' => 'required|string|max:30',
        'is_whatsapp' => 'nullable|bool',
        'label' => 'nullable|string|max:40',
    ];

    public function __construct(Validator $validator, private AthleteService $athletes)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $p = $this->pagination($request);
        $f = $this->validateQuery($request, [
            'status' => 'nullable|in:ativo,inativo,trancado',
            'plan_id' => 'nullable|int|min:1',
            'birth_year' => 'nullable|int|min:1990|max:2100',
            'guardian_id' => 'nullable|int|min:1',
            'health' => 'nullable|bool',
            'sort' => 'nullable|in:name,birth_date,status,enrollment_date,guardian,plan',
            'order' => 'nullable|in:asc,desc',
        ]);

        $result = $this->athletes->list($f + ['search' => $p['search']], $p['page'], $p['per_page'], $this->user($request));

        return $this->paginated($result, $p['page'], $p['per_page']);
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->athletes->get($this->id($request), $this->user($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, true);
        $id = $this->athletes->create($data, $request);

        return JsonResponse::data($this->athletes->get($id, $this->user($request)), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $this->athletes->update($id, $this->validatePayload($request, false), $request);

        return JsonResponse::data($this->athletes->get($id, $this->user($request)));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->athletes->delete($this->id($request), $request);

        return JsonResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';
        $data = $this->validate($request, [
            'name' => "{$req}|string|min:3|max:120",
            'birth_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|in:ativo,inativo,trancado',
            'enrollment_date' => 'sometimes|date',
            'has_health_condition' => 'sometimes|bool',
            'health_condition' => 'sometimes|nullable|text|max:1000',
            'notes' => 'sometimes|nullable|text|max:2000',
            'position_ids' => 'sometimes|ids|max:5',
            'guardian_id' => 'sometimes|nullable|int|min:1',
            'guardian' => 'sometimes|nullable|object',
            'plan' => 'sometimes|nullable|object',
        ]);

        // Objetos aninhados validados com as próprias regras (campos extras descartados).
        if (!empty($data['guardian'])) {
            $data['guardian'] = $this->validateNested($request->body()['guardian'], [
                'name' => 'required|string|min:3|max:120',
                'cpf' => 'nullable|string|max:14',
                'email' => 'nullable|email|max:190',
                'notes' => 'nullable|text|max:2000',
                'phones' => 'required|array|max:5',
            ], 'guardian');
            foreach ($data['guardian']['phones'] as $i => $phone) {
                $data['guardian']['phones'][$i] = $this->validateNested($phone, self::PHONE_RULES, "guardian.phones.{$i}");
            }
        }
        if (!empty($data['plan'])) {
            $data['plan'] = $this->validateNested($request->body()['plan'], [
                'plan_id' => 'required|int|min:1',
                'discount_type' => 'nullable|in:nenhum,cortesia,percentual,valor',
                'discount_value' => 'nullable|money',
                'start_date' => 'nullable|date',
            ], 'plan');
        }

        return $data;
    }
}
