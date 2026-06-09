<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_command_creates_a_company_admin_membership_and_api_token(): void
    {
        $this->artisan('company:bootstrap', [
            'company_name' => 'Acme Assistencia',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@example.com',
            '--password' => 'secret-pass',
            '--company-document' => '12345678000199',
        ])->assertSuccessful();

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Assistencia',
            'document' => '12345678000199',
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $company = Company::query()->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_authenticated_request_exposes_the_active_company_context(): void
    {
        [$plainTextToken, $membership] = $this->createTenantAccess(
            companyName: 'Oficina Central',
            adminEmail: 'tech@example.com',
        );

        $response = $this->withToken($plainTextToken)
            ->getJson('/api/v1/tenant/context');

        $response
            ->assertOk()
            ->assertJsonPath('company.id', $membership->company_id)
            ->assertJsonPath('company.name', 'Oficina Central')
            ->assertJsonPath('membership.id', $membership->getKey())
            ->assertJsonPath('membership.role', 'administrator')
            ->assertJsonPath('user.email', 'tech@example.com');
    }

    public function test_authenticated_bootstrap_returns_active_context_permissions_and_available_memberships(): void
    {
        $user = User::query()->create([
            'name' => 'Operador Multiempresa',
            'email' => 'multi@example.com',
            'password' => 'secret-pass',
        ]);

        $companyA = Company::query()->create([
            'name' => 'Empresa A',
            'email' => 'a@example.com',
            'is_active' => true,
        ]);

        $companyB = Company::query()->create([
            'name' => 'Empresa B',
            'email' => 'b@example.com',
            'is_active' => true,
        ]);

        $membershipA = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $companyA->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $membershipB = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $companyB->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $otherUser = User::query()->create([
            'name' => 'Outro Usuario',
            'email' => 'other@example.com',
            'password' => 'secret-pass',
        ]);

        Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $companyA->getKey(),
            'user_id' => $otherUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $plainTextToken = Str::random(64);

        ApiToken::query()->create([
            'company_id' => $companyA->getKey(),
            'user_id' => $user->getKey(),
            'name' => 'bootstrap-test',
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/auth/bootstrap')
            ->assertOk()
            ->assertJsonPath('context.company.id', $companyA->getKey())
            ->assertJsonPath('context.company.name', 'Empresa A')
            ->assertJsonPath('context.membership.id', $membershipA->getKey())
            ->assertJsonPath('context.membership.role', 'administrator')
            ->assertJsonPath('context.user.email', 'multi@example.com')
            ->assertJsonFragment(['memberships.manage'])
            ->assertJsonCount(2, 'memberships')
            ->assertJsonPath('memberships.0.id', $membershipA->getKey())
            ->assertJsonPath('memberships.0.company_id', $companyA->getKey())
            ->assertJsonPath('memberships.0.is_active_context', true)
            ->assertJsonPath('memberships.1.id', $membershipB->getKey())
            ->assertJsonPath('memberships.1.company_id', $companyB->getKey())
            ->assertJsonPath('memberships.1.is_active_context', false)
            ->assertJsonMissing(['other@example.com']);
    }

    public function test_tenant_scoped_membership_lookup_hides_other_company_records(): void
    {
        [$plainTextToken, $currentMembership] = $this->createTenantAccess(
            companyName: 'Empresa A',
            adminEmail: 'a@example.com',
        );

        [, $otherMembership] = $this->createTenantAccess(
            companyName: 'Empresa B',
            adminEmail: 'b@example.com',
        );

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/tenant/memberships/'.$currentMembership->getKey())
            ->assertOk()
            ->assertJsonPath('data.company_id', $currentMembership->company_id);

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/tenant/memberships/'.$otherMembership->getKey())
            ->assertNotFound();
    }

    /**
     * @return array{string, Membership}
     */
    protected function createTenantAccess(string $companyName, string $adminEmail): array
    {
        $company = Company::query()->create([
            'name' => $companyName,
            'email' => $adminEmail,
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => Str::before($adminEmail, '@'),
            'email' => $adminEmail,
            'password' => 'secret-pass',
        ]);

        $membership = Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $plainTextToken = Str::random(64);

        ApiToken::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'name' => 'test',
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$plainTextToken, $membership];
    }
}
