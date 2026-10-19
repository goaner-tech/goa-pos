<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Written for every sensitive action: voids, menu changes, and later comps, discounts and refunds.
 */
#[Fillable(['employee_id', 'action', 'subject_type', 'subject_id', 'data'])]
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(?Employee $employee, string $action, ?Model $subject = null, array $data = []): self
    {
        return static::create([
            'employee_id' => $employee?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
        ]);
    }
}
