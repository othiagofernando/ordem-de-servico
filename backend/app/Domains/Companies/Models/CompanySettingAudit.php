<?php

namespace App\Domains\Companies\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'actor_membership_id',
    'changed_keys',
    'before_values',
    'after_values',
])]
class CompanySettingAudit extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'changed_keys' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function actorMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'actor_membership_id');
    }
}
