<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'max_users',
    'max_service_orders_per_month',
    'max_attachments_storage_mb',
    'reference_price_cents',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'max_users' => 'integer',
            'max_service_orders_per_month' => 'integer',
            'max_attachments_storage_mb' => 'integer',
            'reference_price_cents' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
