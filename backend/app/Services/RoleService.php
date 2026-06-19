<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    /**
     * Get all roles for a scope
     */
    public function getRoles(string $scope, ?int $tenantId = null): Collection
    {
        $query = Role::where('scope', $scope)->active();

        if ($scope === 'tenant' && $tenantId) {
            // For tenant scope, could add tenant-specific filtering if needed
        }

        return $query->get();
    }

    /**
     * Create a new role
     */
    public function createRole(array $data, ?int $actorId = null, ?int $tenantId = null): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api',
            'scope' => $data['scope'] ?? 'tenant',
            'is_super_admin' => $data['is_super_admin'] ?? false,
            'is_admin' => $data['is_admin'] ?? false,
            'is_active' => true,
        ]);

        // Log action
        $this->auditLog(
            actorId: $actorId,
            action: 'created_role',
            resourceType: 'role',
            resourceId: $role->id,
            scope: $role->scope,
            tenantId: $tenantId,
            changes: ['name' => $role->name]
        );

        return $role;
    }

    /**
     * Update a role
     */
    public function updateRole(Role $role, array $data, ?int $actorId = null, ?int $tenantId = null): Role
    {
        $changes = [];

        if (isset($data['name']) && $data['name'] !== $role->name) {
            $changes['name'] = ['old' => $role->name, 'new' => $data['name']];
            $role->name = $data['name'];
        }

        if (isset($data['is_admin']) && $data['is_admin'] !== $role->is_admin) {
            $changes['is_admin'] = ['old' => $role->is_admin, 'new' => $data['is_admin']];
            $role->is_admin = $data['is_admin'];
        }

        $role->save();

        // Only log if changes were made
        if (!empty($changes)) {
            $this->auditLog(
                actorId: $actorId,
                action: 'updated_role',
                resourceType: 'role',
                resourceId: $role->id,
                scope: $role->scope,
                tenantId: $tenantId,
                changes: $changes
            );
        }

        return $role;
    }

    /**
     * Delete a role (soft delete)
     */
    public function deleteRole(Role $role, ?int $actorId = null, ?int $tenantId = null): bool
    {
        // Log deletion
        $this->auditLog(
            actorId: $actorId,
            action: 'deleted_role',
            resourceType: 'role',
            resourceId: $role->id,
            scope: $role->scope,
            tenantId: $tenantId,
            changes: ['name' => $role->name]
        );

        return (bool) $role->delete();
    }

    /**
     * Restore a soft-deleted role
     */
    public function restoreRole(Role $role, ?int $actorId = null, ?int $tenantId = null): bool
    {
        $this->auditLog(
            actorId: $actorId,
            action: 'restored_role',
            resourceType: 'role',
            resourceId: $role->id,
            scope: $role->scope,
            tenantId: $tenantId,
            changes: ['name' => $role->name]
        );

        return (bool) $role->restore();
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(Role $role, string $permissionName, ?int $actorId = null, ?int $tenantId = null): void
    {
        if (!$role->hasPermissionTo($permissionName)) {
            $role->givePermissionTo($permissionName);

            $this->auditLog(
                actorId: $actorId,
                action: 'assigned_permission_to_role',
                resourceType: 'role_permission',
                resourceId: $role->id,
                scope: $role->scope,
                tenantId: $tenantId,
                changes: ['permission' => $permissionName]
            );
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(Role $role, string $permissionName, ?int $actorId = null, ?int $tenantId = null): void
    {
        if ($role->hasPermissionTo($permissionName)) {
            $role->revokePermissionTo($permissionName);

            $this->auditLog(
                actorId: $actorId,
                action: 'removed_permission_from_role',
                resourceType: 'role_permission',
                resourceId: $role->id,
                scope: $role->scope,
                tenantId: $tenantId,
                changes: ['permission' => $permissionName]
            );
        }
    }

    /**
     * Assign multiple permissions to role
     */
    public function assignPermissionsToRole(Role $role, array $permissionNames, ?int $actorId = null, ?int $tenantId = null): void
    {
        foreach ($permissionNames as $permissionName) {
            $this->assignPermissionToRole($role, $permissionName, $actorId, $tenantId);
        }
    }

    /**
     * Sync permissions for a role (replace all)
     */
    public function syncPermissionsForRole(Role $role, array $permissionNames, ?int $actorId = null, ?int $tenantId = null): void
    {
        $oldPermissions = $role->permissions()->pluck('name')->toArray();
        $role->syncPermissions($permissionNames);

        // Log the change
        $this->auditLog(
            actorId: $actorId,
            action: 'synced_role_permissions',
            resourceType: 'role',
            resourceId: $role->id,
            scope: $role->scope,
            tenantId: $tenantId,
            changes: [
                'old_permissions' => $oldPermissions,
                'new_permissions' => $permissionNames,
            ]
        );
    }

    /**
     * Log audit action
     */
    private function auditLog(
        ?int $actorId,
        string $action,
        string $resourceType,
        ?int $resourceId,
        string $scope,
        ?int $tenantId,
        array $changes = []
    ): void {
        AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => $changes ?: null,
            'scope' => $scope,
            'tenant_id' => $tenantId,
        ]);
    }
}
