<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('sort')->orderBy('name')
            ->with(['items' => fn ($q) => $q->where('active', true), 'items.modifierGroups.modifiers'])
            ->get();

        return response()->json([
            'currency' => config('pos.currency'),
            'categories' => $categories,
        ]);
    }

    /**
     * Marks an item sold out ("86") or back in stock. Managers only.
     */
    public function setSoldOut(Request $request, MenuItem $menuItem): JsonResponse
    {
        abort_unless($request->user()->role->canManage(), 403, 'Only a manager can change the menu.');

        $data = $request->validate(['sold_out' => ['required', 'boolean']]);
        $menuItem->update($data);
        AuditEvent::record($request->user(), 'menu_item.sold_out', $menuItem, $data);

        return response()->json($menuItem);
    }
}
