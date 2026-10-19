<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'role', 'pin', 'active'])]
#[Hidden(['pin'])]
class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'pin' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * PINs are hashed, so a login checks each active employee. A venue has tens of staff, not thousands.
     */
    public static function findByPin(string $pin): ?self
    {
        return static::where('active', true)->get()
            ->first(fn (self $employee) => Hash::check($pin, $employee->pin));
    }
}
