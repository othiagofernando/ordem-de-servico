<?php

namespace App\Domains\Notifications\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'service_order_id',
    'quote_revision_id',
    'event_type',
    'recipient_email',
    'recipient_name',
    'subject',
    'payload',
    'status',
    'error_message',
    'queued_at',
    'sent_at',
    'failed_at',
    'mailable',
    'dedupe_key',
])]
class NotificationDelivery extends Model
{
    use BelongsToCompany;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => AsArrayObject::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function quoteRevision(): BelongsTo
    {
        return $this->belongsTo(QuoteRevision::class);
    }
}
