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
    'received_by_membership_id',
    'amount_cents',
    'currency',
    'payment_method',
    'notes',
    'received_at',
    'receipt_snapshot',
])]
class ServiceOrderPayment extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'receipt_snapshot' => AsArrayObject::class,
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function receivedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'received_by_membership_id');
    }
}
