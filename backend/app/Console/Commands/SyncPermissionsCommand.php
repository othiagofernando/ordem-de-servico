<?php

namespace App\Console\Commands;

use App\Models\PermissionRoute;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

#[Signature('permissions:sync')]
#[Description('Sync permissions from routes/permissions.php to database')]
class SyncPermissionsCommand extends Command
{
    public function handle(): int
    {
        $this->info('🔄 Syncing permissions...');

        $permissionsConfig = include base_path('routes/permissions.php');

        $globalPermissions = $permissionsConfig['global'] ?? [];
        $tenantPermissions = $permissionsConfig['tenant'] ?? [];

        // Sync global permissions
        $this->syncScope('global', $globalPermissions);

        // Sync tenant permissions
        $this->syncScope('tenant', $tenantPermissions);

        $this->info('✅ Permissions synced successfully!');
        return self::SUCCESS;
    }

    private function syncScope(string $scope, array $permissions): void
    {
        $guard = 'api';

        foreach ($permissions as $permissionName => $routes) {
            // Create or get permission
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => $guard],
                ['guard_name' => $guard]
            );

            // Add scope metadata to permission (if your Permission model supports it)
            // For now, we'll store it in permission_routes

            // Sync routes for this permission
            foreach ($routes as $routeName) {
                PermissionRoute::firstOrCreate(
                    [
                        'permission_id' => $permission->id,
                        'route_name' => $routeName,
                        'scope' => $scope,
                    ],
                    [
                        'permission_id' => $permission->id,
                        'route_name' => $routeName,
                        'scope' => $scope,
                    ]
                );
            }

            $this->line("  ✓ {$scope}: {$permissionName}");
        }
    }
}
