<?php

namespace App\Http\Middleware;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Support\Logging\StructuredLogContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(
        protected StructuredLogContext $structuredLogContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $tenantSlug = trim((string) $request->header('X-Tenant-Slug', ''));

        if (! $plainTextToken) {
            return $this->unauthorized('Missing bearer token.');
        }

        $apiToken = ApiToken::query()
            ->with(['company', 'user'])
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if ($apiToken === null || ($apiToken->expires_at !== null && $apiToken->expires_at->isPast())) {
            return $this->unauthorized('Invalid or expired API token.');
        }

        if (! $apiToken->user) {
            return response()->json([
                'message' => 'The token is not bound to a valid user.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($apiToken->user->super_admin && $apiToken->company_id === null) {
            auth()->setUser($apiToken->user);
            app(CurrentCompanyContext::class)->set(null, null, $apiToken->user, $apiToken);
            $this->structuredLogContext->share([
                'company_id' => null,
                'actor_membership_id' => null,
                'actor_user_id' => $apiToken->user_id,
                'api_token_id' => $apiToken->getKey(),
                'is_super_admin' => true,
            ]);

            $apiToken->forceFill([
                'last_used_at' => now(),
            ])->save();

            return $next($request);
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->where('company_id', $apiToken->company_id)
            ->where('user_id', $apiToken->user_id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->first();

        if (
            $membership === null
            || ! $apiToken->company
            || ! $apiToken->company->isOperational()
        ) {
            return response()->json([
                'message' => 'The token is not bound to an active company membership.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($tenantSlug !== '' && $tenantSlug !== (string) $apiToken->company->slug) {
            return response()->json([
                'message' => 'The requested company slug does not match the active company.',
            ], Response::HTTP_NOT_FOUND);
        }

        auth()->setUser($apiToken->user);
        app(CurrentCompanyContext::class)->set($apiToken->company, $membership, $apiToken->user, $apiToken);
        $this->structuredLogContext->share([
            'company_id' => $apiToken->company_id,
            'actor_membership_id' => $membership->getKey(),
            'actor_user_id' => $apiToken->user_id,
            'api_token_id' => $apiToken->getKey(),
        ]);

        $apiToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
