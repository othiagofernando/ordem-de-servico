<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MembershipInvitationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_an_existing_global_identity_and_accept_without_creating_a_duplicate_user(): void
    {
        [$adminToken, $company] = $this->createAdministratorAccess();

        $sharedUser = User::query()->create([
            'name' => 'Shared Identity',
            'email' => 'shared@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Other Company',
            'email' => 'other@example.com',
            'is_active' => true,
        ]);

        Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $otherCompany->getKey(),
            'user_id' => $sharedUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $invite = $this->withToken($adminToken)
            ->postJson('/api/v1/tenant/memberships/invitations', [
                'name' => 'Shared Identity',
                'email' => 'shared@example.com',
                'role' => 'manager',
            ])
            ->assertCreated()
            ->assertJsonPath('data.membership.status', Membership::STATUS_INVITED)
            ->assertJsonPath('data.invitation.requires_password_setup', false);

        $token = $invite->json('data.invitation.token');

        $this->getJson('/api/v1/auth/invitations/'.$token)
            ->assertOk()
            ->assertJsonPath('data.company.uuid', $company->uuid)
            ->assertJsonPath('data.user.email', 'shared@example.com')
            ->assertJsonPath('data.membership.role', 'manager');

        $accept = $this->postJson('/api/v1/auth/invitations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid)
            ->assertJsonPath('membership.status', Membership::STATUS_ACTIVE)
            ->assertJsonPath('membership.role', 'manager');

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseHas('memberships', [
            'company_id' => $company->getKey(),
            'user_id' => $sharedUser->getKey(),
            'status' => Membership::STATUS_ACTIVE,
            'role' => 'manager',
        ]);

        $this->withToken($accept->json('access_token'))
            ->getJson('/api/v1/tenant/context')
            ->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid)
            ->assertJsonPath('user.email', 'shared@example.com');
    }

    public function test_invited_new_identity_can_set_password_on_acceptance_and_then_authenticate(): void
    {
        [$adminToken, $company] = $this->createAdministratorAccess();

        $invite = $this->withToken($adminToken)
            ->postJson('/api/v1/tenant/memberships/invitations', [
                'name' => 'Invited User',
                'email' => 'invited@example.com',
                'role' => 'finance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invitation.requires_password_setup', true);

        $token = $invite->json('data.invitation.token');

        $this->postJson('/api/v1/auth/invitations/'.$token.'/accept', [
            'name' => 'Invited User Accepted',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid)
            ->assertJsonPath('membership.role', 'finance');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'invited@example.com',
            'password' => 'new-secret-pass',
        ])->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid)
            ->assertJsonPath('membership.status', Membership::STATUS_ACTIVE);
    }

    public function test_admin_can_suspend_and_reactivate_memberships_and_list_inactive_records_explicitly(): void
    {
        [$adminToken, $company] = $this->createAdministratorAccess();

        $member = User::query()->create([
            'name' => 'Managed Member',
            'email' => 'managed@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->withToken($adminToken)
            ->patchJson('/api/v1/tenant/memberships/'.$membership->getKey().'/status', [
                'status' => Membership::STATUS_SUSPENDED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Membership::STATUS_SUSPENDED);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'managed@example.com',
            'password' => 'secret-pass',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This identity has no active company memberships.');

        $this->withToken($adminToken)
            ->getJson('/api/v1/tenant/memberships')
            ->assertOk()
            ->assertJsonMissing(['user_email' => 'managed@example.com']);

        $this->withToken($adminToken)
            ->getJson('/api/v1/tenant/memberships?include_inactive=1')
            ->assertOk()
            ->assertJsonFragment([
                'user_email' => 'managed@example.com',
                'status' => Membership::STATUS_SUSPENDED,
            ]);

        $this->withToken($adminToken)
            ->patchJson('/api/v1/tenant/memberships/'.$membership->getKey().'/status', [
                'status' => Membership::STATUS_ACTIVE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Membership::STATUS_ACTIVE);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'managed@example.com',
            'password' => 'secret-pass',
        ])->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid);
    }

    /**
     * @return array{string, Company}
     */
    protected function createAdministratorAccess(): array
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'membership-invite-admin-test')['plain_text_token'];

        return [$token, $company];
    }
}
