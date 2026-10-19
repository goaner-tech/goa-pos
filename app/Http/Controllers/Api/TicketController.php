<?php

namespace App\Http\Controllers\Api;

use App\Enums\LineStatus;
use App\Enums\Station;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Open tickets for one station's screen, oldest first. Bumped tickets from the last 15 minutes come back for recall.
     */
    public function index(Station $station): JsonResponse
    {
        $with = ['lines', 'order.diningTable'];

        $open = Ticket::where('station', $station)->where('status', TicketStatus::Open)
            ->with($with)->oldest()->get();

        $recent = Ticket::where('station', $station)->where('status', TicketStatus::Bumped)
            ->where('bumped_at', '>=', now()->subMinutes(15))
            ->with($with)->latest('bumped_at')->limit(10)->get();

        return response()->json(['open' => $open, 'recent' => $recent, 'now' => now()]);
    }

    public function bump(Request $request, Ticket $ticket, OrderService $orders): JsonResponse
    {
        return response()->json($orders->bump($ticket, $request->user())->load('lines'));
    }

    /**
     * Brings a bumped ticket back to the screen, for when it was bumped by mistake.
     */
    public function recall(Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => TicketStatus::Open, 'bumped_at' => null, 'bumped_by' => null]);
        $ticket->lines()->where('status', LineStatus::Ready)->update(['status' => LineStatus::Fired]);

        return response()->json($ticket->load('lines'));
    }
}
