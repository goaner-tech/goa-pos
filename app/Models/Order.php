<?php

namespace App\Models;

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['service_type', 'dining_table_id', 'tab_name', 'customer_name', 'guest_count', 'status', 'employee_id', 'closed_at'])]
class Order extends Model
{
    protected $appends = ['label', 'total'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => OrderStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Open;
    }

    /**
     * What staff see on screens and tickets: the table, the tab name or the counter customer.
     */
    public function getLabelAttribute(): string
    {
        return match ($this->service_type) {
            ServiceType::Table => 'Table '.($this->diningTable?->name ?? '?'),
            ServiceType::Tab => 'Tab: '.$this->tab_name,
            ServiceType::Counter => 'Counter #'.$this->id.($this->customer_name ? ' ('.$this->customer_name.')' : ''),
        };
    }

    public function getTotalAttribute(): int
    {
        return $this->lines
            ->where('status', '!=', LineStatus::Voided)
            ->sum(fn (OrderLine $line) => $line->lineTotal());
    }
}
