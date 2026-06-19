<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Auth\Models\CompanyRole;
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
            'role' => ['nullable', 'string', Rule::in(array_keys($rolePresetCatalog->all()))],
            'company_role_id' => [
                'nullable',
                'integer',
                Rule::exists('company_roles', 'id')->where('company_id', currentCompany()?->getKey()),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $request->validate([
            'role' => Rule::requiredIf(empty($data['company_role_id'])),
        ]);

        $beforeValues = [
            'role' => $membership->role,
            'company_role_id' => $membership->company_role_id,
            'permissions' => $membership->permissions ?? [],
        ];

        $companyRoleId = $data['company_role_id'] ?? null;
        $membership->forceFill([
            'role' => $companyRoleId ? null : $data['role'],
            'company_role_id' => $companyRoleId,
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
                'company_role_id' => $membership->company_role_id,
                'permissions' => $membership->permissions ?? [],
            ],
        );

        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'company_role_id' => $membership->company_role_id,
                'permissions' => $membership->resolvedPermissions(),
            ],
        ]);
    }
}
