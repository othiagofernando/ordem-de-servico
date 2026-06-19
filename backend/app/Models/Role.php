<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'guard_name',
        'scope',
        'is_super_admin',
        'is_admin',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissionRoutes(): HasMany
    {
        return $this->hasMany(PermissionRoute::class, 'permission_id')
            ->through($this->permissions(), 'id');
    }

    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    public function scopeTenant($query)
    {
        return $query->where('scope', 'tenant');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperAdmin($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }
}
