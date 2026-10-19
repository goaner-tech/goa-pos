<?php

namespace App\Models;

use App\Enums\Station;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['category_id', 'name', 'price', 'station', 'active', 'sold_out', 'sort'])]
class MenuItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'station' => Station::class,
            'active' => 'boolean',
            'sold_out' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class);
    }

    public function isAvailable(): bool
    {
        return $this->active && ! $this->sold_out;
    }
}
