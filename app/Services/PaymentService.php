<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Tender;
use App\Models\CashSession;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes payments against an order and closes it once the bill is covered.
 */
class PaymentService
{
    public function __construct(private BillCalculator $bills, private OrderService $orders) {}

    /**
     * @param  array{tender: string, amount?: int|null, tendered?: int|null, tip?: int|null, reference?: string|null, drawer?: string|null}  $data
     */
    public function pay(Order $order, Employee $employee, array $data): Payment
    {
        return DB::transaction(function () use ($order, $employee, $data) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if (! $order->isOpen()) {
                throw ValidationException::withMessages(['order' => 'This order is no longer open.']);
            }

            $tender = Tender::from($data['tender']);
            $balance = $this->bills->for($order)['balance'];
            $amount = $data['amount'] ?? $balance;
            $tip = $data['tip'] ?? 0;

            if ($balance === 0) {
                throw ValidationException::withMessages(['amount' => 'Nothing is left to pay on this order.']);
            }
            if ($amount < 1 || $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'The amount must be between 1 and the balance due.']);
            }

            $payment = new Payment([
                'order_id' => $order->id,
                'tender' => $tender,
                'amount' => $amount,
                'tip' => $tip,
                'currency' => $tender->currency(),
                'employee_id' => $employee->id,
            ]);

            if ($tender->isCash()) {
                $this->fillCash($payment, $tender, $data, $amount + $tip);
            } else {
                if (blank($data['reference'] ?? null)) {
                    throw ValidationException::withMessages(['reference' => 'Enter the transaction reference from the receipt or SMS.']);
                }
                $payment->tendered = $amount + $tip;
                $payment->reference = $data['reference'];
            }

            $payment->save();
            $order->load('payments', 'lines');

            if ($this->bills->for($order)['balance'] === 0) {
                $this->close($order, $employee);
            }

            return $payment;
        });
    }

    /**
     * Cash goes into an open drawer. Dollars are converted at the manager's rate and change is given in SSP.
     */
    private function fillCash(Payment $payment, Tender $tender, array $data, int $due): void
    {
        $session = CashSession::openFor($data['drawer'] ?? 'main');
        if (! $session) {
            throw ValidationException::withMessages(['drawer' => 'Open the cash drawer before taking cash.']);
        }

        $tendered = $data['tendered'] ?? null;
        if ($tender === Tender::CashUsd) {
            $rate = (int) Setting::get('ssp_per_usd');
            if ($rate < 1) {
                throw ValidationException::withMessages(['tender' => 'A manager must set today\'s dollar rate first.']);
            }
            if (! $tendered) {
                throw ValidationException::withMessages(['tendered' => 'Enter the dollars received.']);
            }
            $payment->exchange_rate = $rate;
            // 1 US cent = rate piasters, because both currencies have 100 minor units.
            $value = $tendered * $rate;
        } else {
            $tendered ??= $due;
            $value = $tendered;
        }

        if ($value < $due) {
            throw ValidationException::withMessages(['tendered' => 'The cash received is less than the amount due.']);
        }

        $payment->tendered = $tendered;
        $payment->change_given = $value - $due;
        $payment->cash_session_id = $session->id;
    }

    /**
     * Records the final bill and sends anything still unsent, so a pay-first counter order reaches the barista.
     */
    private function close(Order $order, Employee $employee): void
    {
        $this->orders->fire($order);

        $bill = $this->bills->for($order);
        $order->update([
            'status' => OrderStatus::Closed,
            'subtotal' => $bill['subtotal'],
            'tax' => $bill['tax'],
            'service_charge' => $bill['service_charge'],
            'closed_at' => now(),
            'closed_by' => $employee->id,
        ]);
    }
}
