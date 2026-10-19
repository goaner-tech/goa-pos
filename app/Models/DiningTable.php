<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'seats', 'section', 'sort'])]
class DiningTable extends Model
{
    public function openOrder(): HasOne
    {
        return $this->hasOne(Order::class)->where('status', OrderStatus::Open);
    }
}
