<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\RolePresetCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipAuthorizationController extends Controller
{
    /**
     * Update a tenant membership authorization profile.
     *
     * @group Auth
     *
     * Applies a default role preset and optional extra granular permissions for one membership.
     */
    public function __invoke(Request $request, Membership $membership, RolePresetCatalog $rolePresetCatalog): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys($rolePresetCatalog->all()))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $membership->forceFill([
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? [],
        ])->save();

        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'permissions' => $membership->resolvedPermissions(),
            ],
        ]);
    }
}
