<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Mail\WelcomeCompanyMail;
use App\Domains\Auth\Support\AuthRateLimiter;
use App\Domains\Companies\Actions\BootstrapCompany;
use App\Domains\Companies\Data\BootstrapCompanyResult;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function __invoke(
        Request $request,
        BootstrapCompany $bootstrapCompany,
        AuthRateLimiter $rateLimiter,
        FrontendTenantUrl $frontendTenantUrl,
    ): JsonResponse {
        $rateLimiter->ensureNotLimited(
            sprintf('public-register:%s', $request->ip() ?: 'unknown'),
            5,
            3600,
        );

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $result = $bootstrapCompany->handle(
            companyName: $data['company_name'],
            adminName: $data['user_name'],
            adminEmail: strtolower($data['user_email']),
            password: $data['password'],
            tokenName: 'self-register',
        );

        Mail::to($result->user->email)->queue(
            new WelcomeCompanyMail(
                companyName: $result->company->name,
                loginUrl: $frontendTenantUrl->login($result->company),
            ),
        );

        return $this->tokenResponse($result);
    }

    protected function tokenResponse(BootstrapCompanyResult $result): JsonResponse
    {
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $result->plainTextToken,
            'expires_at' => $result->apiToken->expires_at?->toIso8601String(),
            'company' => [
                'uuid' => $result->company->uuid,
                'name' => $result->company->name,
                'slug' => $result->company->slug,
            ],
            'membership' => [
                'id' => $result->membership->getKey(),
                'role' => $result->membership->role,
                'status' => $result->membership->status,
            ],
            'user' => [
                'id' => $result->user->getKey(),
                'name' => $result->user->name,
                'email' => $result->user->email,
            ],
        ], Response::HTTP_CREATED);
    }
}
