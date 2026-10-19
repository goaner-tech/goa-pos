<?php

namespace App\Models;

use App\Enums\LineStatus;
use App\Enums\Station;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'menu_item_id', 'ticket_id', 'name', 'station', 'quantity', 'unit_price', 'modifiers', 'notes', 'course', 'seat', 'status', 'void_reason', 'voided_by'])]
class OrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'station' => Station::class,
            'status' => LineStatus::class,
            'modifiers' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'course' => 'integer',
            'seat' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function lineTotal(): int
    {
        return $this->quantity * $this->unit_price;
    }
}
