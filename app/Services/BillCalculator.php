<?php

namespace App\Services;

use App\Enums\LineStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Setting;

/**
 * Works out what a guest owes. All amounts are integer minor units of the venue currency.
 */
class BillCalculator
{
    /**
     * @return array{subtotal: int, service_charge: int, tax: int, tax_inclusive: bool, total: int, paid: int, balance: int}
     */
    public function for(Order $order): array
    {
        $subtotal = $order->lines
            ->where('status', '!=', LineStatus::Voided)
            ->sum(fn (OrderLine $line) => $line->lineTotal());

        $service = intdiv($subtotal * (int) Setting::get('service_charge_bp') + 5000, 10000);
        $taxable = $subtotal + $service;
        $rate = (int) Setting::get('tax_rate_bp');
        $inclusive = (bool) Setting::get('tax_inclusive');

        // Inclusive prices already contain the tax, so it is only shown; exclusive tax is added on top.
        $tax = $inclusive
            ? intdiv($taxable * $rate + intdiv(10000 + $rate, 2), 10000 + $rate)
            : intdiv($taxable * $rate + 5000, 10000);
        $total = $inclusive ? $taxable : $taxable + $tax;
        $paid = (int) $order->payments->sum('amount');

        return [
            'subtotal' => $subtotal,
            'service_charge' => $service,
            'tax' => $tax,
            'tax_inclusive' => $inclusive,
            'total' => $total,
            'paid' => $paid,
            'balance' => max(0, $total - $paid),
        ];
    }
}
