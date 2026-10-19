<?php

namespace App\Services;

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Models\AuditEvent;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The order-to-ticket loop shared by tables, bar tabs and counter orders.
 */
class OrderService
{
    public function open(Employee $employee, array $data): Order
    {
        $type = ServiceType::from($data['service_type']);

        return DB::transaction(function () use ($employee, $data, $type) {
            if ($type === ServiceType::Table) {
                $table = DiningTable::lockForUpdate()->findOrFail($data['dining_table_id']);
                if ($table->openOrder()->exists()) {
                    throw ValidationException::withMessages(['dining_table_id' => "Table {$table->name} already has an open order."]);
                }
            }

            return Order::create([
                'service_type' => $type,
                'dining_table_id' => $type === ServiceType::Table ? $data['dining_table_id'] : null,
                'tab_name' => $type === ServiceType::Tab ? $data['tab_name'] : null,
                'customer_name' => $data['customer_name'] ?? null,
                'guest_count' => $data['guest_count'] ?? null,
                'status' => OrderStatus::Open,
                'employee_id' => $employee->id,
            ]);
        });
    }

    /**
     * Adds an item with its chosen modifiers, copying name and price onto the line.
     *
     * @param  array<int>  $modifierIds
     */
    public function addLine(Order $order, MenuItem $item, array $data, array $modifierIds = []): OrderLine
    {
        $this->ensureOpen($order);

        if (! $item->isAvailable()) {
            throw ValidationException::withMessages(['menu_item_id' => "{$item->name} is not available."]);
        }

        $modifiers = $this->validModifiers($item, $modifierIds);

        return $order->lines()->create([
            'menu_item_id' => $item->id,
            'name' => $item->name,
            'station' => $item->station,
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $item->price + $modifiers->sum('price_delta'),
            'modifiers' => $modifiers->map(fn (Modifier $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price_delta' => $m->price_delta,
            ])->values()->all(),
            'notes' => $data['notes'] ?? null,
            'course' => $data['course'] ?? 1,
            'seat' => $data['seat'] ?? null,
            'status' => LineStatus::New,
        ]);
    }

    /**
     * Sends unsent lines to their stations, one ticket per station. Pass a course to hold later courses back.
     *
     * @return Collection<int, Ticket>
     */
    public function fire(Order $order, ?int $course = null): Collection
    {
        $this->ensureOpen($order);

        return DB::transaction(function () use ($order, $course) {
            $lines = $order->lines()
                ->where('status', LineStatus::New)
                ->when($course !== null, fn ($q) => $q->where('course', $course))
                ->get();

            return $lines->groupBy(fn (OrderLine $line) => $line->station->value)
                ->map(function (Collection $stationLines, string $station) use ($order) {
                    $ticket = $order->tickets()->create(['station' => $station, 'status' => TicketStatus::Open]);
                    OrderLine::whereKey($stationLines->modelKeys())->update([
                        'ticket_id' => $ticket->id,
                        'status' => LineStatus::Fired,
                    ]);

                    return $ticket->load('lines');
                })
                ->values();
        });
    }

    /**
     * Unsent lines can be removed by anyone; once a station has the line, a manager must approve with a reason.
     */
    public function voidLine(OrderLine $line, Employee $employee, ?string $reason): OrderLine
    {
        $this->ensureOpen($line->order);

        if ($line->status === LineStatus::Voided) {
            throw ValidationException::withMessages(['line' => 'This line is already voided.']);
        }

        $sent = $line->status !== LineStatus::New;
        if ($sent && ! $employee->role->canManage()) {
            abort(403, 'A manager must void items already sent to a station.');
        }
        if ($sent && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required to void a sent item.']);
        }

        $line->update([
            'status' => LineStatus::Voided,
            'void_reason' => $reason,
            'voided_by' => $employee->id,
        ]);

        AuditEvent::record($employee, 'order_line.voided', $line, [
            'order_id' => $line->order_id,
            'name' => $line->name,
            'quantity' => $line->quantity,
            'amount' => $line->lineTotal(),
            'was_sent' => $sent,
            'reason' => $reason,
        ]);

        return $line;
    }

    public function bump(Ticket $ticket, Employee $employee): Ticket
    {
        DB::transaction(function () use ($ticket, $employee) {
            $ticket->update([
                'status' => TicketStatus::Bumped,
                'bumped_at' => now(),
                'bumped_by' => $employee->id,
            ]);
            $ticket->lines()->where('status', LineStatus::Fired)->update(['status' => LineStatus::Ready]);
        });

        return $ticket;
    }

    private function ensureOpen(Order $order): void
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages(['order' => 'This order is no longer open.']);
        }
    }

    /**
     * @param  array<int>  $modifierIds
     * @return Collection<int, Modifier>
     */
    private function validModifiers(MenuItem $item, array $modifierIds): Collection
    {
        $groups = $item->modifierGroups()->with('modifiers')->get();
        $allowed = $groups->flatMap->modifiers->keyBy('id');

        $unknown = collect($modifierIds)->reject(fn ($id) => $allowed->has($id));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['modifier_ids' => "Some options don't belong to {$item->name}."]);
        }

        $chosen = collect($modifierIds)->unique()->map(fn ($id) => $allowed->get($id));

        foreach ($groups as $group) {
            $count = $chosen->where('modifier_group_id', $group->id)->count();
            if ($count < $group->min_select || $count > $group->max_select) {
                $range = $group->min_select === $group->max_select
                    ? $group->min_select
                    : "{$group->min_select} to {$group->max_select}";
                throw ValidationException::withMessages(['modifier_ids' => "Choose {$range} for {$group->name}."]);
            }
        }

        return $chosen->values();
    }
}
