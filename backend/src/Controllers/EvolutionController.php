<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Evolution\EvolutionService;

final class EvolutionController extends Controller
{
    public function __construct(Validator $validator, private EvolutionService $evolution)
    {
        parent::__construct($validator);
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->evolution->summary($this->id($request), $this->user($request)));
    }

    // Critérios
    public function criteria(Request $request): JsonResponse
    {
        return JsonResponse::data($this->evolution->criteria());
    }

    public function storeCriterion(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:60',
            'category' => 'required|in:tecnico,tatico,fisico,comportamental',
            'sort_order' => 'nullable|int|min:0|max:9999',
        ]);

        return JsonResponse::data(['id' => $this->evolution->saveCriterion(null, $data, $request)], 201);
    }

    public function updateCriterion(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'sometimes|required|string|min:2|max:60',
            'category' => 'sometimes|required|in:tecnico,tatico,fisico,comportamental',
            'sort_order' => 'sometimes|required|int|min:0|max:9999',
            'active' => 'sometimes|required|bool',
        ]);

        return JsonResponse::data(['id' => $this->evolution->saveCriterion($this->id($request), $data, $request)]);
    }

    // Avaliações
    public function storeEvaluation(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'evaluation_date' => 'required|date',
            'general_comment' => 'nullable|text|max:2000',
            'visible_to_athlete' => 'nullable|bool',
            'scores' => 'required|array|max:50',
        ]);
        foreach ($data['scores'] as $i => $item) {
            $data['scores'][$i] = $this->validateNested($item, [
                'criterion_id' => 'required|int|min:1',
                'score' => 'required|int|min:1|max:5',
            ], "scores.{$i}");
        }
        $id = $this->evolution->addEvaluation($this->id($request), $data, $this->user($request), $request);

        return JsonResponse::data(['id' => $id], 201);
    }

    public function destroyEvaluation(Request $request): JsonResponse
    {
        $this->evolution->deleteEvaluation($this->id($request), $this->user($request), $request);

        return JsonResponse::noContent();
    }

    // Medidas
    public function storeMeasurement(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'measured_at' => 'required|date',
            'height_cm' => 'nullable|numeric|min:50|max:230',
            'weight_kg' => 'nullable|numeric|min:10|max:200',
            'sprint_20m_s' => 'nullable|numeric|min:1|max:20',
            'vertical_jump_cm' => 'nullable|numeric|min:0|max:150',
            'endurance_m' => 'nullable|int|min:0|max:10000',
            'notes' => 'nullable|string|max:255',
        ]);

        return JsonResponse::data(['id' => $this->evolution->addMeasurement($this->id($request), $data, $this->user($request), $request)], 201);
    }

    public function destroyMeasurement(Request $request): JsonResponse
    {
        $this->evolution->deleteMeasurement($this->id($request), $this->user($request), $request);

        return JsonResponse::noContent();
    }

    // Observações
    public function storeNote(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'note_date' => 'required|date',
            'type' => 'required|in:' . implode(',', EvolutionService::NOTE_TYPES),
            'content' => 'required|text|min:3|max:2000',
            'visible_to_athlete' => 'nullable|bool',
        ]);

        return JsonResponse::data(['id' => $this->evolution->addNote($this->id($request), $data, $this->user($request), $request)], 201);
    }

    public function noteVisibility(Request $request): JsonResponse
    {
        $data = $this->validate($request, ['visible_to_athlete' => 'required|bool']);
        $this->evolution->setNoteVisibility($this->id($request), $data['visible_to_athlete'], $this->user($request), $request);

        return JsonResponse::noContent();
    }

    public function destroyNote(Request $request): JsonResponse
    {
        $this->evolution->deleteNote($this->id($request), $this->user($request), $request);

        return JsonResponse::noContent();
    }

    // Metas
    public function storeGoal(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'title' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'target_date' => 'nullable|date',
        ]);

        return JsonResponse::data(['id' => $this->evolution->addGoal($this->id($request), $data, $this->user($request), $request)], 201);
    }

    public function updateGoal(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'title' => 'sometimes|required|string|min:3|max:120',
            'description' => 'sometimes|nullable|string|max:500',
            'target_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|in:' . implode(',', EvolutionService::GOAL_STATUSES),
        ]);
        $this->evolution->updateGoal($this->id($request), $data, $this->user($request), $request);

        return JsonResponse::noContent();
    }

    public function destroyGoal(Request $request): JsonResponse
    {
        $this->evolution->deleteGoal($this->id($request), $this->user($request), $request);

        return JsonResponse::noContent();
    }
}
