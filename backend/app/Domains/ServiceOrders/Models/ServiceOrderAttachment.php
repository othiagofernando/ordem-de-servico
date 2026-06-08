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
    'uploaded_by_membership_id',
    'attachment_type',
    'file_name',
    'storage_key',
    'mime_type',
    'file_size_bytes',
    'notes',
])]
class ServiceOrderAttachment extends Model
{
    use BelongsToCompany;

    public const TYPE_INTAKE_PHOTO = 'intake_photo';

    public const TYPE_DIAGNOSTIC_IMAGE = 'diagnostic_image';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_TERM = 'term';

    public const TYPE_OTHER = 'other';

    public const ALLOWED_TYPES = [
        self::TYPE_INTAKE_PHOTO,
        self::TYPE_DIAGNOSTIC_IMAGE,
        self::TYPE_RECEIPT,
        self::TYPE_TERM,
        self::TYPE_OTHER,
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function uploadedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'uploaded_by_membership_id');
    }
}
