<?php

namespace App\Models;

use App\Enums\Tender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['drawer', 'opened_by', 'float_ssp', 'float_usd', 'closed_at', 'closed_by', 'counted_ssp', 'counted_usd'])]
class CashSession extends Model
{
    protected $appends = ['expected_ssp', 'expected_usd'];

    protected function casts(): array
    {
        return [
            'float_ssp' => 'integer',
            'float_usd' => 'integer',
            'counted_ssp' => 'integer',
            'counted_usd' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function openFor(string $drawer): ?self
    {
        return static::where('drawer', $drawer)->whereNull('closed_at')->latest('id')->first();
    }

    /**
     * Float plus cash taken in, minus change paid out. Change is always given in SSP.
     */
    public function getExpectedSspAttribute(): int
    {
        $payments = $this->payments;

        return $this->float_ssp
            + $payments->where('tender', Tender::CashSsp)->sum('tendered')
            - $payments->filter(fn (Payment $p) => $p->tender->isCash())->sum('change_given');
    }

    public function getExpectedUsdAttribute(): int
    {
        return $this->float_usd + $this->payments->where('tender', Tender::CashUsd)->sum('tendered');
    }
}
