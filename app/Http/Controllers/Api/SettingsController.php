<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['currency' => config('pos.currency'), ...Setting::values()]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->role->canManage(), 403, 'Only a manager can change settings.');

        $data = $request->validate([
            'ssp_per_usd' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
            'tax_rate_bp' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'tax_name' => ['sometimes', 'string', 'max:30'],
            'tax_inclusive' => ['sometimes', 'boolean'],
            'service_charge_bp' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        AuditEvent::record($request->user(), 'settings.updated', null, $data);

        return $this->index();
    }
}
