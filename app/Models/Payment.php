<?php

namespace App\Models;

use App\Enums\Tender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'tender', 'amount', 'tip', 'currency', 'tendered', 'exchange_rate', 'change_given', 'reference', 'cash_session_id', 'employee_id'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'tender' => Tender::class,
            'amount' => 'integer',
            'tip' => 'integer',
            'tendered' => 'integer',
            'exchange_rate' => 'integer',
            'change_given' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
