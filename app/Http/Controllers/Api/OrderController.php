<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\BillCalculator;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanTakeOrders($request);

        $data = $request->validate([
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'dining_table_id' => ['required_if:service_type,table', 'nullable', 'exists:dining_tables,id'],
            'tab_name' => ['required_if:service_type,tab', 'nullable', 'string', 'max:60'],
            'customer_name' => ['nullable', 'string', 'max:60'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $order = $this->orders->open($request->user(), $data);

        return response()->json(self::present($order), 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(self::present($order));
    }

    public function addLine(Request $request, Order $order): JsonResponse
    {
        $this->ensureCanTakeOrders($request);

        $data = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'modifier_ids' => ['nullable', 'array'],
            'modifier_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:200'],
            'course' => ['nullable', 'integer', 'min:1', 'max:5'],
            'seat' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $item = MenuItem::findOrFail($data['menu_item_id']);
        $this->orders->addLine($order, $item, $data, $data['modifier_ids'] ?? []);

        return response()->json(self::present($order), 201);
    }

    public function fire(Request $request, Order $order): JsonResponse
    {
        $this->ensureCanTakeOrders($request);

        $data = $request->validate(['course' => ['nullable', 'integer', 'min:1', 'max:5']]);
        $tickets = $this->orders->fire($order, $data['course'] ?? null);

        return response()->json(['tickets' => $tickets, 'order' => self::present($order)]);
    }

    public function voidLine(Request $request, OrderLine $line): JsonResponse
    {
        $this->ensureCanTakeOrders($request);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $this->orders->voidLine($line, $request->user(), $data['reason'] ?? null);

        return response()->json(self::present($line->order));
    }

    public static function present(Order $order): array
    {
        $order->load(['lines' => fn ($q) => $q->orderBy('course')->orderBy('id'), 'payments', 'diningTable', 'employee:id,name']);

        return [...$order->toArray(), 'bill' => app(BillCalculator::class)->for($order)];
    }

    private function ensureCanTakeOrders(Request $request): void
    {
        abort_unless($request->user()->role->canTakeOrders(), 403, 'Your role cannot take orders.');
    }
}
