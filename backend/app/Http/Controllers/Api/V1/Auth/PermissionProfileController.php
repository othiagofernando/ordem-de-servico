<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PermissionProfileController extends Controller
{
    /**
     * Return the active membership authorization profile.
     *
     * @group Auth
     *
     * Returns the active company membership, its preset role, and the resolved granular permissions.
     */
    public function __invoke(): JsonResponse
    {
        $membership = currentMembership();

        return response()->json([
            'membership' => [
                'id' => $membership?->getKey(),
                'role' => $membership?->role,
                'status' => $membership?->status,
                'permissions' => $membership?->resolvedPermissions() ?? [],
            ],
        ]);
    }
}
