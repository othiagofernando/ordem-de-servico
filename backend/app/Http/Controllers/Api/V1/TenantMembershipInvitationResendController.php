<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Actions\CreateAuthChallenge;
use App\Domains\Auth\Mail\MembershipInvitationMail;
use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class TenantMembershipInvitationResendController extends Controller
{
    public function __invoke(
        Membership $membership,
        CreateAuthChallenge $createAuthChallenge,
        FrontendTenantUrl $frontendTenantUrl,
    ): JsonResponse {
        // Ensure membership belongs to current company
        abort_if(
            $membership->company_id !== currentCompany()?->getKey(),
            Response::HTTP_NOT_FOUND,
        );

        // Only re-invite memberships still in 'invited' status
        abort_unless(
            $membership->status === Membership::STATUS_INVITED,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Este membro já aceitou o convite ou foi removido.',
        );

        $user = $membership->user;
        abort_if($user === null, Response::HTTP_NOT_FOUND);

        $company = currentCompany();

        // Create a fresh challenge (previous may be expired)
        $challenge = $createAuthChallenge->handle(
            user: $user,
            company: $company,
            type: AuthChallenge::TYPE_MEMBERSHIP_INVITATION,
            ttlMinutes: 60 * 24 * 7,
            meta: [
                'membership_id' => $membership->getKey(),
                'role' => $membership->role,
                'requires_password_setup' => ! filled($user->password) || $user->password === null,
                'invited_by_membership_id' => currentMembership()?->getKey(),
            ],
        );

        $plainTextToken = $challenge['plain_text_token'];
        $inviteUrl = $frontendTenantUrl->invitation($company, $plainTextToken);

        $roleLabels = [
            'administrator' => 'Administrador',
            'manager' => 'Gerente',
            'attendant' => 'Atendente',
            'technician' => 'Técnico',
            'finance' => 'Financeiro',
        ];

        $requiresPasswordSetup = ! filled($user->password);

        Mail::to($user->email, $user->name)
            ->queue(new MembershipInvitationMail(
                inviteUrl: $inviteUrl,
                companyName: $company->name,
                inviteeName: $user->name,
                roleName: $roleLabels[$membership->role] ?? $membership->role,
                requiresPasswordSetup: $requiresPasswordSetup,
            ));

        return response()->json([
            'data' => [
                'invitation' => [
                    'token' => $plainTextToken,
                    'accept_url' => $inviteUrl,
                ],
            ],
        ]);
    }
}
