<?php

namespace App\Domains\Auth\Models;

use App\Domains\Auth\Support\RolePresetCatalog;
use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'role', 'company_role_id', 'status', 'permissions'])]
class Membership extends Model
{
    use BelongsToCompany;

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class);
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPermissions(): array
    {
        $basePermissions = $this->company_role_id
            ? ($this->companyRole?->permissions ?? [])
            : app(RolePresetCatalog::class)->permissionsFor($this->role ?? '');

        return array_values(array_unique([...$basePermissions, ...($this->permissions ?? [])]));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->resolvedPermissions(), true);
    }
}
