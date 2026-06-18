<?php

namespace App\Domains\Billing\Models;

use App\Domains\Companies\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'subscription_id',
    'gateway_invoice_id',
    'status',
    'amount_cents',
    'currency',
    'due_at',
    'paid_at',
    'invoice_url',
    'pdf_url',
    'payload',
])]
class BillingInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
