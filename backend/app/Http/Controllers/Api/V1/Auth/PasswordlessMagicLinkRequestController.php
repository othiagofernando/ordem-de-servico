<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\AuthRateLimiter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordlessMagicLinkRequestController extends Controller
{
    /**
     * Request a passwordless magic link.
     *
     * @group Auth
     *
     * Creates a short-lived single-use magic link token when the company allows it.
     */
    public function __invoke(
        Request $request,
        CreateAuthChallenge $createAuthChallenge,
        AuthRateLimiter $rateLimiter,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'company_uuid' => ['required', 'uuid'],
        ]);

        $rateLimiter->ensureNotLimited(
            sprintf('magic-link-request:%s:%s', strtolower($data['email']), $data['company_uuid']),
            5,
            60,
        );

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->with(['user', 'company'])
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('user', fn ($query) => $query->where('email', $data['email']))
            ->whereHas('company', fn ($query) => $query->where('uuid', $data['company_uuid']))
            ->first();

        if ($membership === null || $membership->user === null || $membership->company === null) {
            return response()->json([
                'message' => 'No active membership matches this company and e-mail.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->company->magic_link_enabled) {
            return response()->json([
                'message' => 'Magic link login is disabled for this company.',
            ], Response::HTTP_FORBIDDEN);
        }

        $challenge = $createAuthChallenge->handle(
            user: $membership->user,
            company: $membership->company,
            type: AuthChallenge::TYPE_MAGIC_LINK,
            ttlMinutes: 10,
        );

        $response = [
            'message' => 'A magic link token was issued for this identity.',
        ];

        if (app()->environment('testing')) {
            $response['debug_token'] = $challenge['plain_text_token'];
        }

        return response()->json($response, Response::HTTP_CREATED);
    }
}
