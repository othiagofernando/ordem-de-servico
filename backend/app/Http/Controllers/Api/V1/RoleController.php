<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    /**
     * List roles for a scope
     */
    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope', 'tenant');
        $tenantId = $request->query('tenant_id');

        // Validate scope
        if (!in_array($scope, ['global', 'tenant'])) {
            return response()->json([
                'message' => 'Invalid scope. Must be "global" or "tenant".',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get roles
        $roles = $this->roleService->getRoles($scope, $tenantId);

        return response()->json([
            'data' => $roles->map(fn (Role $role) => $this->serializeRole($role)),
        ]);
    }

    /**
     * Get a single role
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeRole($role),
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
            'scope' => ['required', 'in:global,tenant'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        try {
            $role = $this->roleService->createRole(
                $validated,
                actorId: auth()->id(),
                tenantId: $this->getTenantId($request)
            );

            return response()->json([
                'data' => $this->serializeRole($role),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create role: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Update a role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'unique:roles,name,' . $role->id],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        try {
            $role = $this->roleService->updateRole(
                $role,
                $validated,
                actorId: auth()->id(),
                tenantId: $this->getTenantId($request)
            );

            return response()->json([
                'data' => $this->serializeRole($role),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update role: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Delete a role
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->deleteRole(
                $role,
                actorId: auth()->id(),
                tenantId: $this->getTenantId(request())
            );

            return response()->json([
                'message' => 'Role deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete role: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Assign permission to role
     */
    public function assignPermission(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'string'],
        ]);

        try {
            $this->roleService->assignPermissionToRole(
                $role,
                $validated['permission'],
                actorId: auth()->id(),
                tenantId: $this->getTenantId($request)
            );

            return response()->json([
                'message' => 'Permission assigned to role.',
                'data' => $this->serializeRole($role->load('permissions')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign permission: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermission(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'string'],
        ]);

        try {
            $this->roleService->removePermissionFromRole(
                $role,
                $validated['permission'],
                actorId: auth()->id(),
                tenantId: $this->getTenantId($request)
            );

            return response()->json([
                'message' => 'Permission removed from role.',
                'data' => $this->serializeRole($role->load('permissions')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove permission: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Sync permissions for role (replace all)
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        try {
            $this->roleService->syncPermissionsForRole(
                $role,
                $validated['permissions'],
                actorId: auth()->id(),
                tenantId: $this->getTenantId($request)
            );

            return response()->json([
                'message' => 'Permissions synced.',
                'data' => $this->serializeRole($role->load('permissions')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync permissions: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Serialize role for response
     */
    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'scope' => $role->scope,
            'is_super_admin' => $role->is_super_admin,
            'is_admin' => $role->is_admin,
            'is_active' => $role->is_active,
            'permissions' => $role->permissions?->pluck('name') ?? [],
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'deleted_at' => $role->deleted_at,
        ];
    }

    /**
     * Get tenant ID from request
     */
    private function getTenantId(Request $request): ?int
    {
        if ($tenantSlug = $request->route('tenantSlug')) {
            return auth()->user()->memberships()
                ->whereHas('company', fn ($q) => $q->where('slug', $tenantSlug))
                ->first()?->company_id;
        }

        return null;
    }
}
