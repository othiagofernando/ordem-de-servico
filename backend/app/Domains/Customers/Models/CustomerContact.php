<?php

namespace App\Domains\Customers\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'customer_id',
    'label',
    'name',
    'email',
    'phone',
    'normalized_email',
    'normalized_phone',
])]
class CustomerContact extends Model
{
    use BelongsToCompany;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
