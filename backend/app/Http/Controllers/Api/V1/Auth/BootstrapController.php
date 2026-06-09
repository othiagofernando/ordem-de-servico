<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\ResolveActiveMemberships;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    /**
     * Return the authenticated bootstrap payload for the active company context.
     *
     * @group Auth
     *
     * Returns the active context, resolved permissions, and all active memberships available to the same identity.
     */
    public function __invoke(
        CurrentCompanyContext $context,
        ResolveActiveMemberships $resolveActiveMemberships,
    ): JsonResponse {
        $user = $context->user();
        $activeMembership = $context->membership();

        $memberships = $user === null
            ? collect()
            : $resolveActiveMemberships->handle($user)
                ->sortBy(fn (Membership $membership): string => sprintf(
                    '%d-%s',
                    $membership->getKey() === $activeMembership?->getKey() ? 0 : 1,
                    $membership->company?->name ?? '',
                ))
                ->values();

        return response()->json([
            'context' => [
                'company' => [
                    'id' => $context->company()?->getKey(),
                    'uuid' => $context->company()?->uuid,
                    'name' => $context->company()?->name,
                ],
                'membership' => [
                    'id' => $activeMembership?->getKey(),
                    'role' => $activeMembership?->role,
                    'status' => $activeMembership?->status,
                ],
                'user' => [
                    'id' => $user?->getKey(),
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
            ],
            'permissions' => $activeMembership?->resolvedPermissions() ?? [],
            'memberships' => $memberships->map(fn (Membership $membership): array => [
                'id' => $membership->getKey(),
                'company_id' => $membership->company_id,
                'company_uuid' => $membership->company?->uuid,
                'company_name' => $membership->company?->name,
                'role' => $membership->role,
                'status' => $membership->status,
                'is_active_context' => $membership->getKey() === $activeMembership?->getKey(),
            ])->all(),
        ]);
    }
}
