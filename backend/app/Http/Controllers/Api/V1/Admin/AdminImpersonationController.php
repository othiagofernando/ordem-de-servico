<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminImpersonationController extends Controller
{
    public function memberships(Company $company): JsonResponse
    {
        $memberships = Membership::query()
            ->where('company_id', $company->getKey())
            ->with('user')
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn (Membership $m): array => [
                'id' => $m->getKey(),
                'role' => $m->role,
                'status' => $m->status,
                'user' => [
                    'id' => $m->user?->getKey(),
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                ],
            ])->all(),
        ]);
    }

    public function impersonate(Request $request, Company $company, Membership $membership, IssueApiToken $issueApiToken): JsonResponse
    {
        if ($membership->company_id !== $company->getKey()) {
            abort(404);
        }

        $user = $membership->user;

        if (! $user) {
            abort(422, 'Membership sem usuário associado.');
        }

        $adminId = $request->user()?->getKey();
        $token = $issueApiToken->handle($user, $membership, "impersonated-by-{$adminId}");

        return response()->json([
            'access_token' => $token['plain_text_token'],
            'expires_at' => $token['api_token']->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'membership' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'status' => $membership->status,
            ],
            'company' => [
                'id' => $company->getKey(),
                'uuid' => $company->uuid,
                'name' => $company->name,
            ],
        ]);
    }
}
