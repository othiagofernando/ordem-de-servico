<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MembershipInvitationAcceptController extends Controller
{
    /**
     * Accept a pending company membership invitation.
     *
     * @group Auth
     */
    public function __invoke(Request $request, string $token, IssueApiToken $issueApiToken): JsonResponse
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
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requiresPasswordSetup = (bool) ($challenge->meta['requires_password_setup'] ?? false);

        $data = $request->validate([
            'name' => [$requiresPasswordSetup ? 'required' : 'sometimes', 'string', 'max:255'],
            'password' => [$requiresPasswordSetup ? 'required' : 'sometimes', 'string', 'min:8', 'confirmed'],
        ]);

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
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($challenge, $membership, $requiresPasswordSetup, $data): void {
            if ($requiresPasswordSetup) {
                $challenge->user->forceFill([
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                ])->save();
            }

            $membership->forceFill([
                'status' => Membership::STATUS_ACTIVE,
            ])->save();

            $challenge->forceFill([
                'consumed_at' => now(),
            ])->save();
        });

        $issuedToken = $issueApiToken->handle($challenge->user, $membership->fresh(), 'membership-invitation');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issuedToken['plain_text_token'],
            'expires_at' => $issuedToken['api_token']->expires_at?->toIso8601String(),
            'company' => [
                'uuid' => $challenge->company->uuid,
                'name' => $challenge->company->name,
                'slug' => $challenge->company->slug,
            ],
            'membership' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'status' => Membership::STATUS_ACTIVE,
            ],
            'user' => [
                'name' => $challenge->user->name,
                'email' => $challenge->user->email,
            ],
        ]);
    }
}
