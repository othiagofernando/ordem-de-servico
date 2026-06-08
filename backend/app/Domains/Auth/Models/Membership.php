<?php

namespace App\Domains\Auth\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'role', 'status'])]
class Membership extends Model
{
    use BelongsToCompany;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
