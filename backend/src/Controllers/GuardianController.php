<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Guardians\GuardianService;

final class GuardianController extends Controller
{
    public function __construct(Validator $validator, private GuardianService $guardians)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $p = $this->pagination($request);

        return $this->paginated($this->guardians->list(['search' => $p['search']], $p['page'], $p['per_page']), $p['page'], $p['per_page']);
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->guardians->get($this->id($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $id = $this->guardians->create($this->validatePayload($request), $request);

        return JsonResponse::data($this->guardians->get($id), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $this->guardians->update($id, $this->validatePayload($request), $request);

        return JsonResponse::data($this->guardians->get($id));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->guardians->delete($this->id($request), $request);

        return JsonResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request): array
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:3|max:120',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:190',
            'notes' => 'nullable|text|max:2000',
            'phones' => 'required|array|max:5',
        ]);
        foreach ($data['phones'] as $i => $phone) {
            $data['phones'][$i] = $this->validateNested($phone, [
                'phone' => 'required|string|max:30',
                'is_whatsapp' => 'nullable|bool',
                'label' => 'nullable|string|max:40',
            ], "phones.{$i}");
        }

        return $data;
    }
}
