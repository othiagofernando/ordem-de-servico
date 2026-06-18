<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auth\Support\RolePresetCatalog;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TenantMembershipInvitationController extends Controller
{
    /**
     * Invite a global identity into the active company as a pending membership.
     *
     * @group Auth
     */
    public function __invoke(
        Request $request,
        RolePresetCatalog $rolePresetCatalog,
        CreateAuthChallenge $createAuthChallenge,
        FrontendTenantUrl $frontendTenantUrl,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_keys($rolePresetCatalog->all()))],
        ]);

        $company = currentCompany();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'No active company context is available.');

        $email = strtolower($data['email']);

        $result = DB::transaction(function () use ($company, $email, $data, $createAuthChallenge): array {
            $user = User::query()->where('email', $email)->first();
            $requiresPasswordSetup = false;

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                ]);

                $requiresPasswordSetup = true;
            }

            $existingMembership = Membership::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($existingMembership !== null) {
                abort(Response::HTTP_CONFLICT, 'This identity already has a membership in the active company.');
            }

            $membership = Membership::query()->withoutGlobalScopes()->create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'role' => $data['role'],
                'status' => Membership::STATUS_INVITED,
            ]);

            $challenge = $createAuthChallenge->handle(
                user: $user,
                company: $company,
                type: AuthChallenge::TYPE_MEMBERSHIP_INVITATION,
                ttlMinutes: 60 * 24 * 7,
                meta: [
                    'membership_id' => $membership->getKey(),
                    'role' => $membership->role,
                    'requires_password_setup' => $requiresPasswordSetup,
                    'invited_by_membership_id' => currentMembership()?->getKey(),
                ],
            );

            return [$membership, $challenge['plain_text_token'], $requiresPasswordSetup];
        });

        /** @var array{Membership, string, bool} $result */
        [$membership, $plainTextToken, $requiresPasswordSetup] = $result;

        return response()->json([
            'data' => [
                'membership' => [
                    'id' => $membership->getKey(),
                    'user_id' => $membership->user_id,
                    'role' => $membership->role,
                    'status' => $membership->status,
                ],
                'invitation' => [
                    'token' => $plainTextToken,
                    'accept_url' => $frontendTenantUrl->invitation($company, $plainTextToken),
                    'requires_password_setup' => $requiresPasswordSetup,
                ],
            ],
        ], Response::HTTP_CREATED);
    }
}
