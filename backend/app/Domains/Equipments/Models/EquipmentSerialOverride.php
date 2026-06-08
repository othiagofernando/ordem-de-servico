<?php

namespace App\Domains\Equipments\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'equipment_id',
    'membership_id',
    'serial_number',
    'reason',
])]
class EquipmentSerialOverride extends Model
{
    use BelongsToCompany;

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
