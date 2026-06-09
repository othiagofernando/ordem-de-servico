<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMembershipListController extends Controller
{
    /**
     * List all active memberships for the current company.
     *
     * @group Tenant
     */
    public function __invoke(Request $request): JsonResponse
    {
        $companyId = currentCompany()?->getKey();
        $includeInactive = $request->boolean('include_inactive');

        $membershipsQuery = Membership::query()
            ->where('company_id', $companyId)
            ->with('user')
            ->orderBy('id');

        if (! $includeInactive) {
            $membershipsQuery->where('status', Membership::STATUS_ACTIVE);
        }

        $memberships = $membershipsQuery->get();

        return response()->json([
            'data' => $memberships->map(fn (Membership $m): array => [
                'id' => $m->getKey(),
                'user_id' => $m->user_id,
                'role' => $m->role,
                'status' => $m->status,
                'user_name' => $m->user?->name,
                'user_email' => $m->user?->email,
            ])->all(),
        ]);
    }
}
