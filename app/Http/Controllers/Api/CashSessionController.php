<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CashSessionController extends Controller
{
    public function current(string $drawer): JsonResponse
    {
        return response()->json(['session' => CashSession::openFor($drawer)]);
    }

    public function open(Request $request): JsonResponse
    {
        abort_unless($request->user()->role->canTakeOrders(), 403, 'Your role cannot open a drawer.');

        $data = $request->validate([
            'drawer' => ['required', 'string', 'max:40'],
            'float_ssp' => ['required', 'integer', 'min:0'],
            'float_usd' => ['required', 'integer', 'min:0'],
        ]);

        if (CashSession::openFor($data['drawer'])) {
            throw ValidationException::withMessages(['drawer' => 'This drawer is already open.']);
        }

        $session = CashSession::create([...$data, 'opened_by' => $request->user()->id]);
        AuditEvent::record($request->user(), 'cash_session.opened', $session, $data);

        return response()->json(['session' => $session], 201);
    }

    /**
     * Counting up a drawer is a manager's job; the difference from what the system expects is the variance.
     */
    public function close(Request $request, CashSession $session): JsonResponse
    {
        abort_unless($request->user()->role->canManage(), 403, 'A manager must count up the drawer.');

        $data = $request->validate([
            'counted_ssp' => ['required', 'integer', 'min:0'],
            'counted_usd' => ['required', 'integer', 'min:0'],
        ]);

        if ($session->closed_at) {
            throw ValidationException::withMessages(['session' => 'This drawer is already counted.']);
        }

        $session->update([...$data, 'closed_at' => now(), 'closed_by' => $request->user()->id]);
        $variance = [
            'ssp' => $session->counted_ssp - $session->expected_ssp,
            'usd' => $session->counted_usd - $session->expected_usd,
        ];
        AuditEvent::record($request->user(), 'cash_session.closed', $session, [...$data, 'variance' => $variance]);

        return response()->json(['session' => $session, 'variance' => $variance]);
    }
}
