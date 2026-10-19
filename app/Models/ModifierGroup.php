<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'min_select', 'max_select'])]
class ModifierGroup extends Model
{
    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }
}
