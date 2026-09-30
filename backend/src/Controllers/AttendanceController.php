<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Classes\AttendanceService;
use DateTimeImmutable;

final class AttendanceController extends Controller
{
    public function __construct(Validator $validator, private AttendanceService $attendance)
    {
        parent::__construct($validator);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $this->validate($request, ['date' => 'nullable|date']);

        return JsonResponse::data($this->attendance->open($this->id($request), $data['date'] ?? date('Y-m-d'), $this->user($request)));
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->attendance->get($this->id($request), $this->user($request)));
    }

    public function save(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'records' => 'required|array|max:80',
            'notes' => 'nullable|string|max:255',
        ]);
        $records = [];
        foreach ($data['records'] as $i => $rec) {
            $records[] = $this->validateNested($rec, [
                'athlete_id' => 'required|int|min:1',
                'status' => 'required|in:' . implode(',', AttendanceService::STATUSES),
                'note' => 'nullable|string|max:255',
            ], "records.{$i}");
        }

        return JsonResponse::data($this->attendance->save($this->id($request), $records, $data['notes'] ?? null, $this->user($request), $request));
    }

    public function classReport(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return JsonResponse::data($this->attendance->classReport($this->id($request), $from, $to, $this->user($request)));
    }

    public function athlete(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return JsonResponse::data($this->attendance->athleteStats($this->id($request), $from, $to) + ['from' => $from, 'to' => $to]);
    }

    /** @return array{0: string, 1: string} padrão: últimos 90 dias */
    private function period(Request $request): array
    {
        $q = $this->validateQuery($request, ['from' => 'nullable|date', 'to' => 'nullable|date']);

        return [
            $q['from'] ?? (new DateTimeImmutable('-90 days'))->format('Y-m-d'),
            $q['to'] ?? date('Y-m-d'),
        ];
    }
}
