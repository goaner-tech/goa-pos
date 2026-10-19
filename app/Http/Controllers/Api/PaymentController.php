<?php

namespace App\Http\Controllers\Api;

use App\Enums\Tender;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order, PaymentService $payments): JsonResponse
    {
        abort_unless($request->user()->role->canTakeOrders(), 403, 'Your role cannot take payments.');

        $data = $request->validate([
            'tender' => ['required', Rule::enum(Tender::class)],
            'amount' => ['nullable', 'integer', 'min:1'],
            'tendered' => ['nullable', 'integer', 'min:1'],
            'tip' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'drawer' => ['nullable', 'string', 'max:40'],
        ]);

        $payment = $payments->pay($order, $request->user(), $data);

        return response()->json([
            'payment' => $payment,
            'order' => OrderController::present($order->refresh()),
        ], 201);
    }
}
