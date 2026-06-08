<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'reference', 'title', 'status', 'assigned_membership_id'])]
class ServiceOrder extends Model
{
    use BelongsToCompany;

    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'assigned_membership_id');
    }
}
