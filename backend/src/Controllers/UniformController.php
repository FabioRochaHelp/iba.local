<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Validation\Validator;
use App\Services\Finance\PaymentService;
use App\Services\Uniforms\UniformService;

final class UniformController extends Controller
{
    public function __construct(Validator $validator, private UniformService $uniforms)
    {
        parent::__construct($validator);
    }

    public function items(Request $request): JsonResponse
    {
        return JsonResponse::data($this->uniforms->items((bool) $request->query('active', false)));
    }

    public function storeItem(Request $request): JsonResponse
    {
        $id = $this->uniforms->saveItem(null, $this->itemPayload($request, true), $request);

        return JsonResponse::data(['id' => $id], 201);
    }

    public function updateItem(Request $request): JsonResponse
    {
        $id = $this->uniforms->saveItem($this->id($request), $this->itemPayload($request, false), $request);

        return JsonResponse::data(['id' => $id]);
    }

    public function orders(Request $request): JsonResponse
    {
        $p = $this->pagination($request, 50);
        $f = $this->validateQuery($request, [
            'status' => 'nullable|in:pendente,pago_parcial,pago,cancelado,a_receber',
            'delivery' => 'nullable|in:pendente,entregue',
            'athlete_id' => 'nullable|int|min:1',
            'item_id' => 'nullable|int|min:1',
        ]);
        $result = $this->uniforms->orders($f + ['search' => $p['search']], $p['page'], $p['per_page']);

        return JsonResponse::data($result['items'], 200, [
            'page' => $p['page'],
            'per_page' => $p['per_page'],
            'total' => $result['total'],
            'summary' => $this->uniforms->summary(),
        ]);
    }

    public function showOrder(Request $request): JsonResponse
    {
        return JsonResponse::data($this->uniforms->order($this->id($request)));
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'athlete_id' => 'required|int|min:1',
            'item_id' => 'required|int|min:1',
            'size' => 'nullable|string|max:10',
            'quantity' => 'required|int|min:1|max:20',
            'unit_price' => 'nullable|money',
            'ordered_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
            'payment' => 'nullable|object',
        ]);
        if (!empty($data['payment'])) {
            $data['payment'] = $this->validateNested($request->body()['payment'], $this->paymentRules(), 'payment');
        }

        $id = $this->uniforms->createOrder($data, $this->user($request), $request);

        return JsonResponse::data($this->uniforms->order($id), 201);
    }

    public function pay(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $this->uniforms->pay($id, $this->validate($request, $this->paymentRules()), $this->user($request), $request);

        return JsonResponse::data($this->uniforms->order($id), 201);
    }

    public function deliver(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, ['delivered' => 'required|bool']);
        $this->uniforms->setDelivered($id, $data['delivered'], $this->user($request), $request);

        return JsonResponse::data($this->uniforms->order($id));
    }

    public function cancel(Request $request): JsonResponse
    {
        $id = $this->id($request);
        $data = $this->validate($request, ['reason' => 'required|string|min:3|max:255']);
        $this->uniforms->cancel($id, $data['reason'], $request);

        return JsonResponse::data($this->uniforms->order($id));
    }

    /** @return array<string, string> */
    private function paymentRules(): array
    {
        return [
            'amount' => 'required|money',
            'paid_at' => 'required|date',
            'method' => 'required|in:' . implode(',', PaymentService::METHODS),
            'notes' => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, mixed> */
    private function itemPayload(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';
        $data = $this->validate($request, [
            'name' => "{$req}|string|min:2|max:80",
            'price' => "{$req}|money",
            'sizes' => 'sometimes|nullable|array|max:20',
            'active' => 'sometimes|required|bool',
        ]);
        if (!empty($data['sizes'])) {
            foreach ($data['sizes'] as $i => $size) {
                if (!is_string($size) || !preg_match('/^[\p{L}\d\- ]{1,10}$/u', $size)) {
                    throw \App\Core\Exceptions\ValidationException::withField("sizes.{$i}", 'Tamanho inválido.');
                }
            }
        }

        return $data;
    }
}
