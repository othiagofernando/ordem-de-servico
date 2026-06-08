<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Actions\ResolveActiveMemberships;
use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Password login for internal users.
     *
     * @group Auth
     *
     * Authenticates a global identity and either issues a company-bound token or returns a company selection step.
     */
    public function __invoke(
        Request $request,
        ResolveActiveMemberships $resolveActiveMemberships,
        CreateAuthChallenge $createAuthChallenge,
        IssueApiToken $issueApiToken,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $memberships = $resolveActiveMemberships->handle($user);

        if ($memberships->isEmpty()) {
            return response()->json([
                'message' => 'This identity has no active company memberships.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($memberships->count() === 1) {
            return $this->tokenResponse(
                $issueApiToken->handle($user, $memberships->first(), 'password-login'),
                $memberships->first(),
            );
        }

        $selection = $createAuthChallenge->handle(
            user: $user,
            company: null,
            type: AuthChallenge::TYPE_COMPANY_SELECTION,
            ttlMinutes: 10,
            meta: [
                'membership_ids' => $memberships->pluck('id')->all(),
            ],
        );

        return response()->json([
            'next_step' => 'select_company',
            'selection_token' => $selection['plain_text_token'],
            'companies' => $memberships
                ->map(fn (Membership $membership): array => [
                    'membership_id' => $membership->getKey(),
                    'company_uuid' => $membership->company?->uuid,
                    'company_name' => $membership->company?->name,
                    'role' => $membership->role,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array{plain_text_token: string, api_token: ApiToken}  $issuedToken
     */
    protected function tokenResponse(array $issuedToken, Membership $membership): JsonResponse
    {
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
