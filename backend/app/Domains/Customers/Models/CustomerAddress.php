<?php

namespace App\Domains\Customers\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'customer_id',
    'label',
    'recipient',
    'line_1',
    'line_2',
    'district',
    'city',
    'state',
    'postal_code',
])]
class CustomerAddress extends Model
{
    use BelongsToCompany;

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? preg_replace('/\D+/', '', $value) : $value,
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
