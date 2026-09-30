<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Finance\DelinquencyService;
use App\Services\Finance\FinanceReportService;

final class ReportController extends Controller
{
    public function __construct(
        Validator $validator,
        private DelinquencyService $delinquency,
        private FinanceReportService $reports,
    ) {
        parent::__construct($validator);
    }

    public function delinquency(Request $request): JsonResponse
    {
        return JsonResponse::data($this->delinquency->report());
    }

    public function monthly(Request $request): JsonResponse
    {
        $q = $this->validateQuery($request, ['month' => 'nullable|month']);
        $month = $q['month'] ?? date('Y-m');

        return JsonResponse::data($this->reports->monthly($month) + ['series' => $this->reports->series(6, $month)]);
    }
}
