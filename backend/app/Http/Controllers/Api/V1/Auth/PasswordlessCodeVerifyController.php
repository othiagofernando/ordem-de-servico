<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\AuthRateLimiter;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordlessCodeVerifyController extends Controller
{
    /**
     * Verify a passwordless login code.
     *
     * @group Auth
     *
     * Consumes a short-lived single-use code and issues a company-bound access token.
     */
    public function __invoke(
        Request $request,
        IssueApiToken $issueApiToken,
        AuthRateLimiter $rateLimiter,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'company_uuid' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        $rateLimiter->ensureNotLimited(
            sprintf('passwordless-code-verify:%s:%s', strtolower($data['email']), $data['company_uuid']),
            5,
            60,
        );

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->with(['user', 'company'])
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('user', fn ($query) => $query->where('email', $data['email']))
            ->whereHas('company', fn ($query) => $query
                ->where('uuid', $data['company_uuid'])
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('status', Company::STATUS_ACTIVE))
            ->first();

        if ($membership === null || $membership->user === null || $membership->company === null) {
            return response()->json([
                'message' => 'No active membership matches this company and e-mail.',
            ], Response::HTTP_FORBIDDEN);
        }

        $challenge = AuthChallenge::query()
            ->where('type', AuthChallenge::TYPE_PASSWORDLESS_CODE)
            ->where('user_id', $membership->user_id)
            ->where('company_id', $membership->company_id)
            ->where('code_hash', hash('sha256', $data['code']))
            ->latest('id')
            ->first();

        if (
            $challenge === null
            || $challenge->consumed_at !== null
            || ($challenge->expires_at !== null && $challenge->expires_at->isPast())
        ) {
            return response()->json([
                'message' => 'The login code is invalid, expired, or already used.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $issuedToken = $issueApiToken->handle($membership->user, $membership, 'passwordless-code');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issuedToken['plain_text_token'],
            'expires_at' => $issuedToken['api_token']->expires_at?->toIso8601String(),
            'company' => [
                'uuid' => $membership->company->uuid,
                'name' => $membership->company->name,
            ],
        ]);
    }
}
