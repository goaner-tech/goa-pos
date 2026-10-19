<?php

namespace Tests\Feature;

use App\Enums\LineStatus;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actAs(Role $role): Employee
    {
        $employee = Employee::where('role', $role)->firstOrFail();
        Sanctum::actingAs($employee);

        return $employee;
    }

    private function item(string $name): MenuItem
    {
        return MenuItem::where('name', $name)->firstOrFail();
    }

    private function modifierId(MenuItem $item, string $name): int
    {
        return $item->modifierGroups()->with('modifiers')->get()
            ->flatMap->modifiers->firstWhere('name', $name)->id;
    }

    public function test_staff_log_in_with_their_pin(): void
    {
        $this->postJson('/api/login', ['pin' => '2222'])
            ->assertOk()
            ->assertJsonPath('employee.role', 'server')
            ->assertJsonStructure(['token']);

        $this->postJson('/api/login', ['pin' => '9999'])->assertUnprocessable();
    }

    public function test_api_requires_login(): void
    {
        $this->getJson('/api/menu')->assertUnauthorized();
    }

    public function test_a_table_cannot_have_two_open_orders(): void
    {
        $this->actAs(Role::Server);
        $table = DiningTable::first();

        $this->postJson('/api/orders', ['service_type' => 'table', 'dining_table_id' => $table->id, 'guest_count' => 2])
            ->assertCreated()
            ->assertJsonPath('label', 'Table '.$table->name);

        $this->postJson('/api/orders', ['service_type' => 'table', 'dining_table_id' => $table->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dining_table_id');
    }

    public function test_bar_tab_needs_a_name(): void
    {
        $this->actAs(Role::Bartender);

        $this->postJson('/api/orders', ['service_type' => 'tab'])->assertJsonValidationErrors('tab_name');
        $this->postJson('/api/orders', ['service_type' => 'tab', 'tab_name' => 'James'])
            ->assertCreated()
            ->assertJsonPath('label', 'Tab: James');
    }

    public function test_line_price_includes_modifiers_and_is_copied_at_sale(): void
    {
        $this->actAs(Role::Cashier);
        $order = $this->postJson('/api/orders', ['service_type' => 'counter', 'customer_name' => 'Ayen'])->json();
        $cappuccino = $this->item('Cappuccino');

        $this->postJson("/api/orders/{$order['id']}/lines", [
            'menu_item_id' => $cappuccino->id,
            'quantity' => 2,
            'modifier_ids' => [
                $this->modifierId($cappuccino, 'Large'),
                $this->modifierId($cappuccino, 'Oat milk'),
                $this->modifierId($cappuccino, 'Extra shot'),
            ],
        ])->assertCreated()
            ->assertJsonPath('lines.0.unit_price', 600000 + 100000 + 50000 + 50000)
            ->assertJsonPath('total', 2 * 800000);

        $cappuccino->update(['price' => 999900]);
        $this->getJson("/api/orders/{$order['id']}")->assertJsonPath('total', 1600000);
    }

    public function test_required_modifier_choices_are_enforced(): void
    {
        $this->actAs(Role::Server);
        $order = Order::create(['service_type' => 'tab', 'tab_name' => 'X', 'employee_id' => 1]);
        $steak = $this->item('Grilled beef steak');

        $this->postJson("/api/orders/{$order->id}/lines", ['menu_item_id' => $steak->id])
            ->assertJsonValidationErrors('modifier_ids');

        $this->postJson("/api/orders/{$order->id}/lines", [
            'menu_item_id' => $steak->id,
            'modifier_ids' => [$this->modifierId($steak, 'Rare'), $this->modifierId($steak, 'Medium'), $this->modifierId($steak, 'Rice')],
        ])->assertJsonValidationErrors('modifier_ids');

        $this->postJson("/api/orders/{$order->id}/lines", [
            'menu_item_id' => $steak->id,
            'modifier_ids' => [$this->modifierId($this->item('Cappuccino'), 'Large')],
        ])->assertJsonValidationErrors('modifier_ids');
    }

    public function test_sold_out_items_cannot_be_ordered_and_only_managers_can_86(): void
    {
        $this->actAs(Role::Server);
        $samosa = $this->item('Vegetable samosa (3)');
        $this->patchJson("/api/menu-items/{$samosa->id}/sold-out", ['sold_out' => true])->assertForbidden();

        $this->actAs(Role::Manager);
        $this->patchJson("/api/menu-items/{$samosa->id}/sold-out", ['sold_out' => true])->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'menu_item.sold_out']);

        $order = Order::create(['service_type' => 'tab', 'tab_name' => 'X', 'employee_id' => 1]);
        $this->postJson("/api/orders/{$order->id}/lines", ['menu_item_id' => $samosa->id])
            ->assertJsonValidationErrors('menu_item_id');
    }

    public function test_firing_splits_lines_into_one_ticket_per_station_and_holds_later_courses(): void
    {
        $this->actAs(Role::Server);
        $order = $this->postJson('/api/orders', ['service_type' => 'table', 'dining_table_id' => DiningTable::first()->id])->json();
        $steak = $this->item('Grilled beef steak');

        $add = fn (array $line) => $this->postJson("/api/orders/{$order['id']}/lines", $line)->assertCreated();
        $add(['menu_item_id' => $this->item('Nile Special')->id, 'quantity' => 2]);
        $add(['menu_item_id' => $this->item('Ful medames')->id, 'course' => 1]);
        $add(['menu_item_id' => $steak->id, 'course' => 2, 'modifier_ids' => [$this->modifierId($steak, 'Medium'), $this->modifierId($steak, 'Chips')]]);

        $this->postJson("/api/orders/{$order['id']}/fire", ['course' => 1])
            ->assertOk()
            ->assertJsonCount(2, 'tickets');

        $this->getJson('/api/stations/bar/tickets')->assertJsonCount(1, 'open')->assertJsonPath('open.0.lines.0.name', 'Nile Special');
        $this->getJson('/api/stations/kitchen/tickets')->assertJsonCount(1, 'open')->assertJsonCount(1, 'open.0.lines');

        $this->postJson("/api/orders/{$order['id']}/fire", ['course' => 2])->assertJsonCount(1, 'tickets');
        $this->getJson('/api/stations/kitchen/tickets')->assertJsonCount(2, 'open');

        $this->postJson("/api/orders/{$order['id']}/fire")->assertJsonCount(0, 'tickets');
    }

    public function test_bumping_marks_lines_ready_and_recall_brings_the_ticket_back(): void
    {
        $this->actAs(Role::Bartender);
        $order = $this->postJson('/api/orders', ['service_type' => 'tab', 'tab_name' => 'Peter'])->json();
        $this->postJson("/api/orders/{$order['id']}/lines", ['menu_item_id' => $this->item('White Bull lager')->id])->assertCreated();
        $ticketId = $this->postJson("/api/orders/{$order['id']}/fire")->json('tickets.0.id');

        $this->postJson("/api/tickets/{$ticketId}/bump")->assertOk()->assertJsonPath('lines.0.status', 'ready');
        $this->getJson('/api/stations/bar/tickets')->assertJsonCount(0, 'open')->assertJsonCount(1, 'recent');

        $this->postJson("/api/tickets/{$ticketId}/recall")->assertOk();
        $this->getJson('/api/stations/bar/tickets')->assertJsonCount(1, 'open');
    }

    public function test_voiding_a_sent_item_needs_a_manager_and_a_reason(): void
    {
        $this->actAs(Role::Server);
        $order = $this->postJson('/api/orders', ['service_type' => 'tab', 'tab_name' => 'Sarah'])->json();
        $beer = $this->item('Nile Special')->id;
        $unsent = $this->postJson("/api/orders/{$order['id']}/lines", ['menu_item_id' => $beer])->json('lines.0.id');

        $this->postJson("/api/order-lines/{$unsent}/void")->assertOk()->assertJsonPath('total', 0);

        $this->postJson("/api/orders/{$order['id']}/lines", ['menu_item_id' => $beer])->assertCreated();
        $this->postJson("/api/orders/{$order['id']}/fire")->assertOk();
        $sent = Order::find($order['id'])->lines()->where('status', LineStatus::Fired)->value('id');

        $this->postJson("/api/order-lines/{$sent}/void", ['reason' => 'Spilled'])->assertForbidden();

        $this->actAs(Role::Manager);
        $this->postJson("/api/order-lines/{$sent}/void")->assertJsonValidationErrors('reason');
        $this->postJson("/api/order-lines/{$sent}/void", ['reason' => 'Spilled'])->assertOk()->assertJsonPath('total', 0);

        $this->assertSame(2, AuditEvent::where('action', 'order_line.voided')->count());
    }

    public function test_kitchen_staff_cannot_take_orders(): void
    {
        $this->actAs(Role::Kitchen);
        $this->postJson('/api/orders', ['service_type' => 'counter'])->assertForbidden();
        $this->getJson('/api/stations/kitchen/tickets')->assertOk();
    }
}
