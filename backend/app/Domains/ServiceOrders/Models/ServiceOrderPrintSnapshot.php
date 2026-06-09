<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'service_order_id',
    'version',
    'payload',
    'first_issued_by_membership_id',
    'first_issued_at',
    'last_issued_by_membership_id',
    'last_issued_at',
    'issue_count',
])]
class ServiceOrderPrintSnapshot extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'first_issued_at' => 'datetime',
            'last_issued_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function firstIssuedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'first_issued_by_membership_id');
    }

    public function lastIssuedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'last_issued_by_membership_id');
    }
}
