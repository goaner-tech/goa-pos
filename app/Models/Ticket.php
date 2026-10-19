<?php

namespace App\Models;

use App\Enums\Station;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'station', 'status', 'bumped_at', 'bumped_by'])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'station' => Station::class,
            'status' => TicketStatus::class,
            'bumped_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }
}
