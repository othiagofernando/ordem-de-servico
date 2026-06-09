<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MembershipInvitationShowController extends Controller
{
    /**
     * Show the current invitation payload for a membership invite token.
     *
     * @group Auth
     */
    public function __invoke(string $token): JsonResponse
    {
        $challenge = AuthChallenge::query()
            ->with(['user', 'company'])
            ->where('type', AuthChallenge::TYPE_MEMBERSHIP_INVITATION)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (
            $challenge === null
            || $challenge->consumed_at !== null
            || ($challenge->expires_at !== null && $challenge->expires_at->isPast())
            || $challenge->user === null
            || $challenge->company === null
        ) {
            return response()->json([
                'message' => 'The invitation token is invalid, expired, or already used.',
            ], Response::HTTP_NOT_FOUND);
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->where('id', $challenge->meta['membership_id'] ?? null)
            ->where('company_id', $challenge->company_id)
            ->where('user_id', $challenge->user_id)
            ->where('status', Membership::STATUS_INVITED)
            ->first();

        if ($membership === null) {
            return response()->json([
                'message' => 'This invitation no longer maps to a pending company membership.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'company' => [
                    'uuid' => $challenge->company->uuid,
                    'name' => $challenge->company->name,
                ],
                'user' => [
                    'name' => $challenge->user->name,
                    'email' => $challenge->user->email,
                ],
                'membership' => [
                    'id' => $membership->getKey(),
                    'role' => $membership->role,
                    'status' => $membership->status,
                ],
                'requires_password_setup' => (bool) ($challenge->meta['requires_password_setup'] ?? false),
                'expires_at' => $challenge->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
