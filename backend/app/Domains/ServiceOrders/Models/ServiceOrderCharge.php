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
    'source_quote_revision_id',
    'created_by_membership_id',
    'charge_type',
    'description',
    'amount_cents',
    'currency',
    'metadata',
])]
class ServiceOrderCharge extends Model
{
    use BelongsToCompany;

    public const TYPE_APPROVED_QUOTE = 'approved_quote';

    public const TYPE_DIAGNOSTIC_FEE = 'diagnostic_fee';

    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => AsArrayObject::class,
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function sourceQuoteRevision(): BelongsTo
    {
        return $this->belongsTo(QuoteRevision::class, 'source_quote_revision_id');
    }

    public function createdByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'created_by_membership_id');
    }
}
