<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TenantMembershipController extends Controller
{
    /**
     * Return a tenant-scoped membership record.
     *
     * @group Tenant
     *
     * Memberships outside the active company context are hidden by the tenant scope.
     */
    public function __invoke(Membership $membership): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'company_id' => $membership->company_id,
                'user_id' => $membership->user_id,
                'role' => $membership->role,
                'status' => $membership->status,
            ],
        ]);
    }
}
