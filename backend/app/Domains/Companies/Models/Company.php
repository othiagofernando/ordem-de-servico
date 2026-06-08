<?php

namespace App\Domains\Companies\Models;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'document', 'email', 'phone', 'is_active'])]
#[Hidden(['created_at', 'updated_at'])]
class Company extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            if (! $company->uuid) {
                $company->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}
