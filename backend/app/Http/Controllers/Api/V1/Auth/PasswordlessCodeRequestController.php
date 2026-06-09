<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Mail\PasswordlessCodeMail;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\AuthRateLimiter;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class PasswordlessCodeRequestController extends Controller
{
    /**
     * Request a passwordless login code.
     *
     * @group Auth
     *
     * Creates a short-lived single-use login code when the company allows e-mail code login.
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
            sprintf('passwordless-code-request:%s:%s', strtolower($data['email']), $data['company_uuid']),
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

        if (! $membership->company->passwordless_code_enabled) {
            return response()->json([
                'message' => 'Passwordless code login is disabled for this company.',
            ], Response::HTTP_FORBIDDEN);
        }

        $challenge = $createAuthChallenge->handle(
            user: $membership->user,
            company: $membership->company,
            type: AuthChallenge::TYPE_PASSWORDLESS_CODE,
            ttlMinutes: 10,
            withNumericCode: true,
        );

        Mail::to($membership->user->email)->send(
            new PasswordlessCodeMail($challenge['plain_text_code'], $membership->company->name),
        );

        $response = [
            'message' => 'A login code was issued for this identity.',
        ];

        if (app()->environment('testing')) {
            $response['debug_code'] = $challenge['plain_text_code'];
        }

        return response()->json($response, Response::HTTP_CREATED);
    }
}
