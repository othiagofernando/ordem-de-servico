<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelectCompanyController extends Controller
{
    /**
     * Complete authentication by choosing the active company.
     *
     * @group Auth
     *
     * Consumes a short-lived company selection token and issues a company-bound access token.
     */
    public function __invoke(Request $request, IssueApiToken $issueApiToken): JsonResponse
    {
        $data = $request->validate([
            'selection_token' => ['required', 'string'],
            'company_uuid' => ['required', 'uuid'],
        ]);

        $challenge = AuthChallenge::query()
            ->with('user')
            ->where('type', AuthChallenge::TYPE_COMPANY_SELECTION)
            ->where('token_hash', hash('sha256', $data['selection_token']))
            ->first();

        if (
            $challenge === null
            || $challenge->consumed_at !== null
            || ($challenge->expires_at !== null && $challenge->expires_at->isPast())
            || $challenge->user === null
        ) {
            return response()->json([
                'message' => 'The company selection token is invalid or expired.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->with('company')
            ->where('user_id', $challenge->user_id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query->where('uuid', $data['company_uuid']))
            ->first();

        if ($membership === null) {
            return response()->json([
                'message' => 'The selected company is not available for this authenticated identity.',
            ], Response::HTTP_FORBIDDEN);
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $issuedToken = $issueApiToken->handle($challenge->user, $membership, 'company-selection');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issuedToken['plain_text_token'],
            'expires_at' => $issuedToken['api_token']->expires_at?->toIso8601String(),
            'company' => [
                'uuid' => $membership->company?->uuid,
                'name' => $membership->company?->name,
            ],
            'membership' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'status' => $membership->status,
            ],
        ]);
    }
}
