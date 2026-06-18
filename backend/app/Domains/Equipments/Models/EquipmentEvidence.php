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
    'uploaded_by_membership_id',
    'file_name',
    'storage_key',
    'mime_type',
    'file_size_bytes',
    'caption',
])]
class EquipmentEvidence extends Model
{
    use BelongsToCompany;

    protected $table = 'equipment_evidences';

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function uploadedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'uploaded_by_membership_id');
    }
}
