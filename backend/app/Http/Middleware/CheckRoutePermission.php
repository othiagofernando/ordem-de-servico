<?php

namespace App\Http\Middleware;

use App\Models\PermissionRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        // Skip auth-less routes
        if (!$routeName || !auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Global scope routes (/admin/*)
        if (str_starts_with($routeName, 'admin.')) {
            return $this->validateGlobalRoute($user, $routeName, $request);
        }

        // Tenant scope routes (/tenant/*)
        if (str_starts_with($routeName, 'tenant.') || str_starts_with($request->path(), 'tenant')) {
            return $this->validateTenantRoute($user, $routeName, $request);
        }

        return $next($request);
    }

    private function validateGlobalRoute($user, string $routeName, Request $request): Response
    {
        // SUPER_ADMIN bypass: Check if user has any super_admin role
        if ($user->hasRole(function ($query) {
            return $query->where('is_super_admin', true);
        }, 'api')) {
            return $next($request);
        }

        // Check if user has permission for this route
        if ($this->userHasPermissionForRoute($user, $routeName, 'global')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized access to this resource.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function validateTenantRoute($user, string $routeName, Request $request): Response
    {
        $tenantSlug = $request->route('tenantSlug');

        if (!$tenantSlug) {
            return response()->json([
                'message' => 'Missing tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user is admin of this tenant (bypass)
        $membership = $user->memberships()
            ->whereHas('company', fn ($q) => $q->where('slug', $tenantSlug))
            ->first();

        if ($membership && $membership->isAdminOfTenant()) {
            return $next($request);
        }

        // Check if user has permission for this route in this tenant
        if ($this->userHasPermissionForRoute($user, $routeName, 'tenant')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized access to this resource.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function userHasPermissionForRoute($user, string $routeName, string $scope): bool
    {
        // Get permissions required for this route
        $permissions = PermissionRoute::where('route_name', $routeName)
            ->where('scope', $scope)
            ->with('permission')
            ->get();

        if ($permissions->isEmpty()) {
            return true; // No permission required for this route
        }

        // Check if user has ANY of the required permissions
        foreach ($permissions as $permRoute) {
            if ($user->hasPermissionTo($permRoute->permission->name)) {
                return true;
            }
        }

        return false;
    }
}
