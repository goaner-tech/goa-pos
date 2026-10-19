<?php

namespace Tests\Feature;

use App\Enums\LineStatus;
use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
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

    /**
     * An open bar tab holding two Nile Specials (SSP 5,500 each) = SSP 11,000.
     */
    private function tabWithBeers(): array
    {
        $order = $this->postJson('/api/orders', ['service_type' => 'tab', 'tab_name' => 'Gatluak'])->json();
        $beer = MenuItem::where('name', 'Nile Special')->value('id');

        return $this->postJson("/api/orders/{$order['id']}/lines", ['menu_item_id' => $beer, 'quantity' => 2])->json();
    }

    private function openDrawer(int $floatSsp = 0, int $floatUsd = 0): int
    {
        return $this->postJson('/api/cash-sessions', ['drawer' => 'main', 'float_ssp' => $floatSsp, 'float_usd' => $floatUsd])
            ->assertCreated()->json('session.id');
    }

    public function test_cash_needs_an_open_drawer(): void
    {
        $this->actAs(Role::Bartender);
        $order = $this->tabWithBeers();

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp'])
            ->assertJsonValidationErrors('drawer');
    }

    public function test_paying_in_full_with_ssp_cash_gives_change_and_closes_the_order(): void
    {
        $this->actAs(Role::Bartender);
        $this->openDrawer();
        $order = $this->tabWithBeers();
        $this->assertSame(1100000, $order['bill']['total']);

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp', 'tendered' => 1500000])
            ->assertCreated()
            ->assertJsonPath('payment.change_given', 400000)
            ->assertJsonPath('order.status', 'closed')
            ->assertJsonPath('order.bill.balance', 0);

        $this->assertSame(1100000, Order::find($order['id'])->subtotal);
    }

    public function test_usd_cash_is_converted_at_the_managers_rate_with_change_in_ssp(): void
    {
        $this->actAs(Role::Bartender);
        $this->openDrawer();
        $order = $this->tabWithBeers();

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_usd', 'tendered' => 500])
            ->assertJsonValidationErrors('tender');

        Setting::put('ssp_per_usd', 4500);

        // $5 = SSP 22,500 against SSP 11,000 due: SSP 11,500 change.
        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_usd', 'tendered' => 500])
            ->assertCreated()
            ->assertJsonPath('payment.currency', 'USD')
            ->assertJsonPath('payment.exchange_rate', 4500)
            ->assertJsonPath('payment.change_given', 1150000)
            ->assertJsonPath('order.status', 'closed');
    }

    public function test_split_payment_with_mobile_money_needs_a_reference(): void
    {
        $this->actAs(Role::Server);
        $this->openDrawer();
        $order = $this->tabWithBeers();

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp', 'amount' => 600000])
            ->assertCreated()
            ->assertJsonPath('order.status', 'open')
            ->assertJsonPath('order.bill.balance', 500000);

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'mobile_money'])
            ->assertJsonValidationErrors('reference');

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'mobile_money', 'amount' => 600000, 'reference' => 'MG123'])
            ->assertJsonValidationErrors('amount');

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'mobile_money', 'reference' => 'MG123', 'tip' => 100000])
            ->assertCreated()
            ->assertJsonPath('payment.amount', 500000)
            ->assertJsonPath('payment.tendered', 600000)
            ->assertJsonPath('order.status', 'closed');

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp'])
            ->assertJsonValidationErrors('order');
    }

    public function test_service_charge_and_exclusive_tax_are_added_to_the_bill(): void
    {
        $this->actAs(Role::Bartender);
        Setting::put('service_charge_bp', 1000);
        Setting::put('tax_rate_bp', 1800);
        Setting::put('tax_inclusive', false);

        $bill = $this->tabWithBeers()['bill'];

        $this->assertSame(1100000, $bill['subtotal']);
        $this->assertSame(110000, $bill['service_charge']);
        $this->assertSame(217800, $bill['tax']);
        $this->assertSame(1427800, $bill['total']);
    }

    public function test_inclusive_tax_is_shown_but_not_added(): void
    {
        $this->actAs(Role::Bartender);
        Setting::put('tax_rate_bp', 1800);

        $bill = $this->tabWithBeers()['bill'];

        $this->assertSame(1100000, $bill['total']);
        $this->assertSame(167797, $bill['tax']);
    }

    public function test_paying_a_counter_order_sends_it_to_the_barista_and_frees_tables_on_close(): void
    {
        $this->actAs(Role::Cashier);
        $this->openDrawer();
        $order = $this->postJson('/api/orders', ['service_type' => 'counter', 'customer_name' => 'Nyibol'])->json();
        $tea = MenuItem::where('name', 'Spiced tea')->firstOrFail();
        $small = $tea->modifierGroups()->first()->modifiers()->where('name', 'Small')->value('id');
        $this->postJson("/api/orders/{$order['id']}/lines", ['menu_item_id' => $tea->id, 'modifier_ids' => [$small]])->assertCreated();

        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp'])->assertCreated();

        $this->getJson('/api/stations/barista/tickets')->assertJsonCount(1, 'open');
        $this->assertSame(LineStatus::Fired, Order::find($order['id'])->lines->first()->status);

        $table = DiningTable::first();
        $tableOrder = $this->postJson('/api/orders', ['service_type' => 'table', 'dining_table_id' => $table->id])->json();
        $this->postJson("/api/orders/{$tableOrder['id']}/lines", ['menu_item_id' => $tea->id, 'modifier_ids' => [$small]]);
        $this->postJson("/api/orders/{$tableOrder['id']}/payments", ['tender' => 'cash_ssp'])->assertCreated();
        $this->postJson('/api/orders', ['service_type' => 'table', 'dining_table_id' => $table->id])->assertCreated();
    }

    public function test_drawer_count_shows_variance_against_expected_cash(): void
    {
        $this->actAs(Role::Bartender);
        Setting::put('ssp_per_usd', 4500);
        $session = $this->openDrawer(5000000, 2000);
        $order = $this->tabWithBeers();
        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_usd', 'tendered' => 500])->assertCreated();

        $this->postJson("/api/cash-sessions/{$session}/close", ['counted_ssp' => 0, 'counted_usd' => 0])->assertForbidden();

        // Expected: SSP 50,000 float - 11,500 change = 38,500; USD 20 float + 5 = 25.
        $this->actAs(Role::Manager);
        $this->postJson("/api/cash-sessions/{$session}/close", ['counted_ssp' => 3800000, 'counted_usd' => 2500])
            ->assertOk()
            ->assertJsonPath('variance.ssp', -50000)
            ->assertJsonPath('variance.usd', 0);
    }

    public function test_day_report_totals_sales_by_tender_for_managers(): void
    {
        $this->actAs(Role::Bartender);
        $this->openDrawer();
        $order = $this->tabWithBeers();
        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'cash_ssp', 'amount' => 100000]);
        $this->postJson("/api/orders/{$order['id']}/payments", ['tender' => 'mobile_money', 'reference' => 'X1']);
        $this->getJson('/api/reports/day')->assertForbidden();

        $this->actAs(Role::Manager);
        $report = $this->getJson('/api/reports/day')->assertOk()->json();

        $this->assertSame(1, $report['orders']);
        $this->assertSame(1100000, $report['subtotal']);
        $this->assertSame(1100000, $report['collected']);
        $this->assertSame(1000000, collect($report['tenders'])->firstWhere('tender', 'mobile_money')['amount']);
        $this->assertSame('Nile Special', $report['top_items'][0]['name']);
    }

    public function test_only_managers_change_settings(): void
    {
        $this->actAs(Role::Cashier);
        $this->patchJson('/api/settings', ['ssp_per_usd' => 5000])->assertForbidden();

        $this->actAs(Role::Manager);
        $this->patchJson('/api/settings', ['ssp_per_usd' => 5000, 'tax_inclusive' => false])
            ->assertOk()
            ->assertJsonPath('ssp_per_usd', 5000)
            ->assertJsonPath('tax_inclusive', false);
    }
}
