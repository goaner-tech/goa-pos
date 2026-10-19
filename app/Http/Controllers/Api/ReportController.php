<?php

namespace App\Http\Controllers\Api;

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\Tender;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * The end-of-day (Z) report: sales, how they were paid, voids, and drawers, for one business day.
     */
    public function day(Request $request): JsonResponse
    {
        abort_unless($request->user()->role->canManage(), 403, 'Only a manager can see reports.');

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $from = Carbon::parse($data['date'] ?? today()->toDateString())->startOfDay();
        $to = $from->copy()->endOfDay();

        $orders = Order::where('status', OrderStatus::Closed)->whereBetween('closed_at', [$from, $to])->get();
        $payments = Payment::whereBetween('created_at', [$from, $to])->get();
        $voids = AuditEvent::where('action', 'order_line.voided')->whereBetween('created_at', [$from, $to])->get();

        $topItems = OrderLine::query()
            ->whereIn('order_id', $orders->modelKeys())
            ->where('status', '!=', LineStatus::Voided)
            ->selectRaw('name, sum(quantity) as quantity, sum(quantity * unit_price) as sales')
            ->groupBy('name')->orderByDesc('sales')->limit(10)->get();

        return response()->json([
            'date' => $from->toDateString(),
            'orders' => $orders->count(),
            'guests' => (int) $orders->sum('guest_count'),
            'subtotal' => (int) $orders->sum('subtotal'),
            'service_charge' => (int) $orders->sum('service_charge'),
            'tax' => (int) $orders->sum('tax'),
            'tips' => (int) $payments->sum('tip'),
            'tenders' => collect(Tender::cases())->map(fn (Tender $t) => [
                'tender' => $t->value,
                'count' => $payments->where('tender', $t)->count(),
                'amount' => (int) $payments->where('tender', $t)->sum('amount'),
                'tendered' => (int) $payments->where('tender', $t)->sum('tendered'),
                'currency' => $t->currency(),
            ])->values(),
            'collected' => (int) $payments->sum('amount'),
            'voids' => ['count' => $voids->count(), 'amount' => (int) $voids->sum(fn ($v) => $v->data['amount'] ?? 0)],
            'open_orders' => Order::where('status', OrderStatus::Open)->count(),
            'top_items' => $topItems,
            'drawers' => CashSession::where(fn ($q) => $q->whereBetween('created_at', [$from, $to])->orWhereNull('closed_at'))->get(),
        ]);
    }
}
