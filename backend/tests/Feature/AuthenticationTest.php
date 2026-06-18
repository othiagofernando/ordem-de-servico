<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_issues_a_company_bound_token_for_a_single_active_membership(): void
    {
        [$company] = $this->createMembershipSet();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid)
            ->assertJsonStructure(['access_token', 'company', 'membership']);

        $this->withToken($response->json('access_token'))
            ->getJson('/api/v1/tenant/context')
            ->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid);
    }

    public function test_suspended_membership_cannot_complete_login(): void
    {
        $user = User::query()->create([
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Suspended Co',
            'email' => 'suspended@example.com',
            'is_active' => true,
        ]);

        Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_SUSPENDED,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'secret-pass',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This identity has no active company memberships.');
    }

    public function test_multiple_memberships_require_company_selection_and_bind_the_token_to_the_chosen_company(): void
    {
        [$companyA, $user] = $this->createMembershipSet();

        $companyB = Company::query()->create([
            'name' => 'Beta Assistencia',
            'email' => 'beta@example.com',
            'is_active' => true,
        ]);

        Membership::query()->create([
            'company_id' => $companyB->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'secret-pass',
        ]);

        $login->assertOk()
            ->assertJsonPath('next_step', 'select_company')
            ->assertJsonCount(2, 'companies');

        $select = $this->postJson('/api/v1/auth/select-company', [
            'selection_token' => $login->json('selection_token'),
            'company_uuid' => $companyB->uuid,
        ]);

        $select->assertOk()
            ->assertJsonPath('company.uuid', $companyB->uuid);

        $this->withToken($select->json('access_token'))
            ->getJson('/api/v1/tenant/context')
            ->assertOk()
            ->assertJsonPath('company.uuid', $companyB->uuid)
            ->assertJsonPath('membership.role', 'manager');
    }

    public function test_super_admin_login_issues_a_token_without_company_context(): void
    {
        User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'root@example.com',
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('company', null)
            ->assertJsonPath('membership', null)
            ->assertJsonPath('user.super_admin', true);

        $token = $response->json('access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/bootstrap')
            ->assertOk()
            ->assertJsonPath('context.company.id', null)
            ->assertJsonPath('context.membership.id', null)
            ->assertJsonPath('context.user.super_admin', true)
            ->assertJsonPath('permissions.0', 'super_admin');

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => User::query()->where('email', 'root@example.com')->value('id'),
            'company_id' => null,
        ]);
    }

    public function test_suspended_company_cannot_authenticate_even_with_active_membership(): void
    {
        [$company] = $this->createMembershipSet();

        $company->forceFill([
            'status' => Company::STATUS_SUSPENDED,
            'is_active' => false,
        ])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'secret-pass',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This identity has no active company memberships.');
    }

    public function test_passwordless_code_and_magic_link_are_single_use(): void
    {
        [$company] = $this->createMembershipSet(
            passwordlessCodeEnabled: true,
            magicLinkEnabled: true,
        );

        $codeRequest = $this->postJson('/api/v1/auth/passwordless/code/request', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
        ]);

        $codeRequest->assertCreated();

        $code = $codeRequest->json('debug_code');

        $this->postJson('/api/v1/auth/passwordless/code/verify', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/auth/passwordless/code/verify', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The login code is invalid, expired, or already used.');

        $magicRequest = $this->postJson('/api/v1/auth/passwordless/magic-link/request', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
        ]);

        $magicRequest->assertCreated();

        $token = $magicRequest->json('debug_token');

        $this->postJson('/api/v1/auth/passwordless/magic-link/consume', [
            'token' => $token,
        ])->assertOk();

        $this->postJson('/api/v1/auth/passwordless/magic-link/consume', [
            'token' => $token,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The magic link token is invalid, expired, or already used.');

        $this->assertDatabaseHas('auth_challenges', [
            'type' => AuthChallenge::TYPE_PASSWORDLESS_CODE,
        ]);
        $this->assertDatabaseHas('auth_challenges', [
            'type' => AuthChallenge::TYPE_MAGIC_LINK,
        ]);
    }

    public function test_suspended_company_blocks_passwordless_authentication_paths(): void
    {
        [$company] = $this->createMembershipSet(
            passwordlessCodeEnabled: true,
            magicLinkEnabled: true,
        );

        $company->forceFill([
            'status' => Company::STATUS_SUSPENDED,
            'is_active' => false,
        ])->save();

        $this->postJson('/api/v1/auth/passwordless/lookup', [
            'email' => 'operator@example.com',
        ])->assertOk()
            ->assertJsonCount(0, 'companies');

        $this->postJson('/api/v1/auth/passwordless/code/request', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
        ])->assertForbidden();

        $this->postJson('/api/v1/auth/passwordless/magic-link/request', [
            'email' => 'operator@example.com',
            'company_uuid' => $company->uuid,
        ])->assertForbidden();
    }

    /**
     * @return array{Company, User}
     */
    protected function createMembershipSet(
        bool $passwordlessCodeEnabled = false,
        bool $magicLinkEnabled = false,
    ): array {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'operator@example.com',
            'is_active' => true,
            'passwordless_code_enabled' => $passwordlessCodeEnabled,
            'magic_link_enabled' => $magicLinkEnabled,
        ]);

        Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        return [$company, $user];
    }
}
