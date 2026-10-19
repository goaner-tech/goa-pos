<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Venue settings a manager changes from the till, such as the dollar rate and tax.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** Defaults used until a manager sets a value. Rates are basis points (1600 = 16%). */
    public const DEFAULTS = [
        'ssp_per_usd' => 0,
        'tax_rate_bp' => 0,
        'tax_name' => 'Tax',
        'tax_inclusive' => true,
        'service_charge_bp' => 0,
        'receipt_footer' => 'Thank you!',
    ];

    public static function get(string $key): mixed
    {
        $value = static::find($key)?->value;
        if ($value === null) {
            return self::DEFAULTS[$key] ?? null;
        }

        return json_decode($value, true);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }

    /** @return array<string, mixed> */
    public static function values(): array
    {
        return collect(self::DEFAULTS)->keys()->mapWithKeys(fn ($key) => [$key => static::get($key)])->all();
    }
}
