<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'service_order_id',
    'revision_number',
    'status',
    'line_items',
    'currency',
    'total_cents',
    'notes',
    'valid_until',
    'submitted_at',
    'decisioned_at',
    'decision_token_hash',
    'decision_ip',
    'decision_user_agent',
    'decision_idempotency_key',
])]
class QuoteRevision extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_items' => AsArrayObject::class,
            'valid_until' => 'datetime',
            'submitted_at' => 'datetime',
            'decisioned_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
