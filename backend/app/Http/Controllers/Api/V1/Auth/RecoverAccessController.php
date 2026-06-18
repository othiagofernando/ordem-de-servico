<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Mail\MagicLinkMail;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\AuthRateLimiter;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RecoverAccessController extends Controller
{
    /**
     * Send a recovery link for the given email and company.
     *
     * @group Auth
     *
     * Sends a one-time magic link regardless of whether the company has magic-link login enabled.
     * Always responds with 200 to avoid user enumeration.
     */
    public function __invoke(
        Request $request,
        CreateAuthChallenge $createAuthChallenge,
        AuthRateLimiter $rateLimiter,
        FrontendTenantUrl $frontendTenantUrl,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'company_uuid' => ['required', 'uuid'],
        ]);

        $rateLimiter->ensureNotLimited(
            sprintf('recover-access:%s:%s', strtolower($data['email']), $data['company_uuid']),
            3,
            60,
        );

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->with(['user', 'company'])
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->where('email', $data['email']))
            ->whereHas('company', fn ($q) => $q
                ->where('uuid', $data['company_uuid'])
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('status', Company::STATUS_ACTIVE))
            ->first();

        $response = ['message' => 'Se este e-mail está cadastrado, um link de recuperação foi enviado.'];

        if ($membership === null || $membership->user === null || $membership->company === null) {
            return response()->json($response);
        }

        $challenge = $createAuthChallenge->handle(
            user: $membership->user,
            company: $membership->company,
            type: AuthChallenge::TYPE_MAGIC_LINK,
            ttlMinutes: 15,
        );

        $magicLinkUrl = $frontendTenantUrl->magicLink($membership->company, $challenge['plain_text_token']);

        Mail::to($membership->user->email)->send(
            new MagicLinkMail($magicLinkUrl, $membership->company->name),
        );

        if (app()->environment('testing')) {
            $response['debug_token'] = $challenge['plain_text_token'];
        }

        return response()->json($response);
    }
}
