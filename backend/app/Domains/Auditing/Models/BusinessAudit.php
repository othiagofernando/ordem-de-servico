<?php

namespace App\Domains\Auditing\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'company_id',
    'actor_membership_id',
    'category',
    'event_type',
    'auditable_type',
    'auditable_id',
    'context',
    'before_values',
    'after_values',
    'occurred_at',
])]
class BusinessAudit extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actorMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'actor_membership_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
