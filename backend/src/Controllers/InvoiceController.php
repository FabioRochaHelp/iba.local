<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Finance\InvoiceGeneratorService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;

final class InvoiceController extends Controller
{
    public function __construct(
        Validator $validator,
        private InvoiceService $invoices,
        private InvoiceGeneratorService $generator,
        private PaymentService $payments,
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): JsonResponse
    {
        $p = $this->pagination($request, 50);
        $f = $this->validateQuery($request, [
            'month' => 'nullable|month',
            'status' => 'nullable|in:aberta,paga,parcial,cortesia,cancelada,atrasada,pendente',
            'athlete_id' => 'nullable|int|min:1',
            'sort' => 'nullable|in:athlete,due_date,final_amount,status,reference_month',
            'order' => 'nullable|in:asc,desc',
        ]);

        return $this->paginated($this->invoices->list($f + ['search' => $p['search']], $p['page'], $p['per_page']), $p['page'], $p['per_page']);
    }

    public function show(Request $request): JsonResponse
    {
        return JsonResponse::data($this->invoices->get($this->id($request)));
    }

    public function forAthlete(Request $request): JsonResponse
    {
        return JsonResponse::data($this->invoices->forAthlete($this->id($request)));
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $this->validate($request, ['month' => 'required|month']);

        return JsonResponse::data($this->generator->generate($data['month'], $request));
    }

    public function update(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, [
            'due_date' => 'sometimes|required|date',
            'discount' => 'sometimes|required|money',
            'notes' => 'sometimes|nullable|string|max:255',
        ]);
        $this->invoices->update($id, $data, $request);

        return JsonResponse::data($this->invoices->get($id));
    }

    public function cancel(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, ['reason' => 'required|string|min:3|max:255']);
        $this->invoices->cancel($id, $data['reason'], $this->user($request), $request);

        return JsonResponse::data($this->invoices->get($id));
    }

    public function pay(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, [
            'amount' => 'required|money',
            'paid_at' => 'required|date',
            'method' => 'required|in:' . implode(',', PaymentService::METHODS),
            'notes' => 'nullable|string|max:255',
        ]);
        $this->payments->register($id, $data, $this->user($request), $request);

        return JsonResponse::data($this->invoices->get($id), 201);
    }

    public function reversePayment(Request $request): JsonResponse
    {
        $data = $this->validate($request, ['reason' => 'required|string|min:3|max:255']);
        $this->payments->reverse($this->id($request), $data['reason'], $this->user($request), $request);

        return JsonResponse::noContent();
    }
}
