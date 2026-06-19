<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\CompanyRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRoleController extends Controller
{
    /**
     * List all custom roles for the current company.
     *
     * @group Tenant
     */
    public function index(): JsonResponse
    {
        $roles = CompanyRole::query()->orderBy('name')->get();

        return response()->json([
            'data' => $roles->map(fn (CompanyRole $r): array => $this->serialize($r))->all(),
        ]);
    }

    /**
     * Create a new custom role for the current company.
     *
     * @group Tenant
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = CompanyRole::create([
            'name' => $data['name'],
            'permissions' => $data['permissions'] ?? [],
        ]);

        return response()->json(['data' => $this->serialize($role)], 201);
    }

    /**
     * Update a custom role.
     *
     * @group Tenant
     */
    public function update(Request $request, CompanyRole $companyRole): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $companyRole->update($data);

        return response()->json(['data' => $this->serialize($companyRole)]);
    }

    /**
     * Delete a custom role (memberships using it will fall back to null).
     *
     * @group Tenant
     */
    public function destroy(CompanyRole $companyRole): JsonResponse
    {
        $companyRole->delete();

        return response()->json(['message' => 'Perfil removido.']);
    }

    private function serialize(CompanyRole $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'permissions' => $role->permissions ?? [],
            'memberships_count' => $role->memberships_count ?? null,
            'created_at' => $role->created_at?->toISOString(),
        ];
    }
}
