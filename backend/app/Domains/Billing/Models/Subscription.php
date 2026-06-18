<?php

namespace App\Domains\Billing\Models;

use App\Domains\Companies\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'plan_id',
    'started_at',
    'expires_at',
    'status',
    'gateway_customer_id',
    'gateway_subscription_id',
    'gateway_name',
])]
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const GRACE_DAYS = 3;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function isAccessible(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->expires_at->copy()->addDays(self::GRACE_DAYS)->isFuture();
    }

    public function daysUntilExpiration(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }
}
