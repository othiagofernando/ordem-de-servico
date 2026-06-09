<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
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
    public function __invoke(
        Request $request,
        Membership $membership,
        RolePresetCatalog $rolePresetCatalog,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys($rolePresetCatalog->all()))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $beforeValues = [
            'role' => $membership->role,
            'permissions' => $membership->permissions ?? [],
        ];

        $membership->forceFill([
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? [],
        ])->save();

        $recordBusinessAudit->handle(
            $membership->company_id,
            'membership',
            'membership_authorization_changed',
            auditable: $membership,
            actorMembership: currentMembership(),
            context: [
                'membership_id' => $membership->getKey(),
                'user_id' => $membership->user_id,
            ],
            beforeValues: $beforeValues,
            afterValues: [
                'role' => $membership->role,
                'permissions' => $membership->permissions ?? [],
            ],
        );

        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'permissions' => $membership->resolvedPermissions(),
            ],
        ]);
    }
}
