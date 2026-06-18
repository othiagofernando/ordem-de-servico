<?php

namespace App\Domains\Equipments\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Domains\Customers\Models\Customer;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'customer_id',
    'category',
    'brand',
    'model',
    'serial_number',
    'accessories',
    'notes',
])]
class Equipment extends Model
{
    use BelongsToCompany;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serialOverrides(): HasMany
    {
        return $this->hasMany(EquipmentSerialOverride::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(EquipmentEvidence::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
