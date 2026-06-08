<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'service_order_id',
    'actor_membership_id',
    'type',
    'visibility',
    'title',
    'payload',
    'occurred_at',
])]
class ServiceOrderTimelineEvent extends Model
{
    use BelongsToCompany;

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_CUSTOMER = 'customer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => AsArrayObject::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function actorMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'actor_membership_id');
    }
}
