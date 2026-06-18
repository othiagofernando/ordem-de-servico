<?php

namespace App\Domains\Customers\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'customer_type', 'name', 'trade_name', 'document', 'notes'])]
class Customer extends Model
{
    use BelongsToCompany;

    protected function document(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? preg_replace('/[^A-Za-z0-9]/', '', $value) : $value,
        );
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
