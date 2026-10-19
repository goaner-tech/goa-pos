<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    /**
     * The floor: every table with its open order, plus open bar tabs and counter orders.
     */
    public function floor(): JsonResponse
    {
        $tables = DiningTable::orderBy('sort')->orderBy('name')
            ->with('openOrder.lines')
            ->get();

        $others = Order::where('status', OrderStatus::Open)
            ->whereIn('service_type', [ServiceType::Tab, ServiceType::Counter])
            ->with('lines')
            ->oldest()
            ->get();

        return response()->json([
            'tables' => $tables,
            'tabs' => $others->where('service_type', ServiceType::Tab)->values(),
            'counter' => $others->where('service_type', ServiceType::Counter)->values(),
        ]);
    }
}
