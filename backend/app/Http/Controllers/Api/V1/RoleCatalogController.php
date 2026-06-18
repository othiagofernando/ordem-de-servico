<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Support\RolePresetCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RoleCatalogController extends Controller
{
    /**
     * Return all available roles and their permission presets.
     *
     * @group Tenant
     */
    public function __invoke(RolePresetCatalog $catalog): JsonResponse
    {
        $roles = collect($catalog->all())
            ->map(fn (array $permissions, string $role): array => [
                'role' => $role,
                'permissions' => $permissions,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $roles]);
    }
}
