<?php

namespace App\Domains\Companies\Models;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'trade_name',
    'document',
    'email',
    'phone',
    'address_line_1',
    'address_line_2',
    'district',
    'city',
    'state',
    'postal_code',
    'website',
    'logo_path',
    'is_active',
    'status',
    'passwordless_code_enabled',
    'magic_link_enabled',
])]
#[Hidden(['created_at', 'updated_at'])]
class Company extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            if (! $company->uuid) {
                $company->uuid = (string) Str::uuid();
            }

            if (! $company->slug) {
                $base = Str::slug($company->name ?: 'empresa') ?: 'empresa';
                $slug = $base;
                $suffix = 2;

                while (self::query()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                $company->slug = $slug;
            }
        });

        static::created(function (self $company): void {
            if ($company->subscription()->exists()) {
                return;
            }

            $starterPlan = Plan::query()->where('slug', 'starter')->first();

            if ($starterPlan === null) {
                return;
            }

            Subscription::query()->create([
                'company_id' => $company->getKey(),
                'plan_id' => $starterPlan->getKey(),
                'started_at' => now(),
                'expires_at' => now()->addDays(30),
                'status' => Subscription::STATUS_ACTIVE,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'passwordless_code_enabled' => 'bool',
            'magic_link_enabled' => 'bool',
            'deleted_at' => 'datetime',
        ];
    }

    public function isOperational(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->is_active && $this->deleted_at === null;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    protected function document(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? preg_replace('/[^A-Za-z0-9]/', '', $value) : $value,
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? preg_replace('/\D+/', '', $value) : $value,
        );
    }

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? preg_replace('/\D+/', '', $value) : $value,
        );
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function operationalSetting(): HasOne
    {
        return $this->hasOne(CompanyOperationalSetting::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function settingAudits(): HasMany
    {
        return $this->hasMany(CompanySettingAudit::class);
    }
}
