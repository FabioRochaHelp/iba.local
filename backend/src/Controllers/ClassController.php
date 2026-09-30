<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Classes\ClassService;

final class ClassController extends Controller
{
    public function __construct(Validator $validator, private ClassService $classes)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $f = $this->validateQuery($request, [
            'active' => 'nullable|bool',
            'weekday' => 'nullable|int|min:1|max:7',
        ]);

        return JsonResponse::data($this->classes->list($this->user($request), $f));
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->classes->get($this->id($request), $this->user($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $id = $this->classes->save(null, $this->payload($request, true), $request);

        return JsonResponse::data($this->classes->get($id, $this->user($request)), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->classes->save($this->id($request), $this->payload($request, false), $request);

        return JsonResponse::data($this->classes->get($id, $this->user($request)));
    }

    public function setAthletes(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, ['athlete_ids' => 'present|ids|max:60']);
        $this->classes->setAthletes($id, $data['athlete_ids'] ?? [], $request);

        return JsonResponse::data($this->classes->get($id, $this->user($request)));
    }

    public function suggestions(Request $request): JsonResponse
    {
        return JsonResponse::data($this->classes->suggestions($this->id($request)));
    }

    public function coaches(Request $request): JsonResponse
    {
        return JsonResponse::data($this->classes->coaches());
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return $this->validate($request, [
            'name' => "{$req}|string|min:3|max:80",
            'weekday' => "{$req}|int|min:1|max:7",
            'start_time' => "{$req}|time",
            'end_time' => "{$req}|time",
            'category' => 'sometimes|nullable|string|max:20',
            'min_birth_year' => 'sometimes|nullable|int|min:1990|max:2100',
            'max_birth_year' => 'sometimes|nullable|int|min:1990|max:2100',
            'coach_id' => 'sometimes|nullable|int|min:1',
            'active' => 'sometimes|required|bool',
        ]);
    }
}
