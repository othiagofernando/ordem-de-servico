<?php

namespace App\Domains\Auth\Models;

use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'company_id',
    'type',
    'token_hash',
    'code_hash',
    'expires_at',
    'consumed_at',
    'meta',
])]
class AuthChallenge extends Model
{
    public const TYPE_COMPANY_SELECTION = 'company_selection';

    public const TYPE_PASSWORDLESS_CODE = 'passwordless_code';

    public const TYPE_MAGIC_LINK = 'magic_link';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
