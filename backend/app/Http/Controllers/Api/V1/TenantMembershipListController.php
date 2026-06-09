<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TenantMembershipListController extends Controller
{
    /**
     * List all active memberships for the current company.
     *
     * @group Tenant
     */
    public function __invoke(): JsonResponse
    {
        $companyId = currentCompany()?->getKey();

        $memberships = Membership::query()
            ->where('company_id', $companyId)
            ->where('status', Membership::STATUS_ACTIVE)
            ->with('user')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn (Membership $m): array => [
                'id' => $m->getKey(),
                'role' => $m->role,
                'status' => $m->status,
                'user_name' => $m->user?->name,
                'user_email' => $m->user?->email,
            ])->all(),
        ]);
    }
}
