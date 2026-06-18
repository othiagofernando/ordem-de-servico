<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordlessMagicLinkConsumeController extends Controller
{
    /**
     * Consume a passwordless magic link token.
     *
     * @group Auth
     *
     * Consumes a short-lived single-use magic link token and issues a company-bound access token.
     */
    public function __invoke(Request $request, IssueApiToken $issueApiToken): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $challenge = AuthChallenge::query()
            ->with(['user', 'company'])
            ->where('type', AuthChallenge::TYPE_MAGIC_LINK)
            ->where('token_hash', hash('sha256', $data['token']))
            ->first();

        if (
            $challenge === null
            || $challenge->consumed_at !== null
            || ($challenge->expires_at !== null && $challenge->expires_at->isPast())
            || $challenge->user === null
            || $challenge->company === null
        ) {
            return response()->json([
                'message' => 'The magic link token is invalid, expired, or already used.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->with('company')
            ->where('user_id', $challenge->user_id)
            ->where('company_id', $challenge->company_id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('status', Company::STATUS_ACTIVE))
            ->first();

        if ($membership === null) {
            return response()->json([
                'message' => 'This magic link no longer maps to an active company membership.',
            ], Response::HTTP_FORBIDDEN);
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $issuedToken = $issueApiToken->handle($challenge->user, $membership, 'magic-link');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issuedToken['plain_text_token'],
            'expires_at' => $issuedToken['api_token']->expires_at?->toIso8601String(),
            'company' => [
                'uuid' => $membership->company?->uuid,
                'name' => $membership->company?->name,
                'slug' => $membership->company?->slug,
            ],
        ]);
    }
}
