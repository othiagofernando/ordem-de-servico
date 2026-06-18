<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantUrlRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tenant_lookup_returns_active_company_by_slug(): void
    {
        $company = Company::query()->create([
            'name' => 'TechFix Central',
            'slug' => 'techfix-central',
            'email' => 'contato@techfix.test',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/public/tenants/techfix-central')
            ->assertOk()
            ->assertJsonPath('data.id', $company->getKey())
            ->assertJsonPath('data.slug', 'techfix-central');

        $this->getJson('/api/v1/public/tenants/inexistente')
            ->assertNotFound();
    }

    public function test_authenticated_requests_reject_mismatched_tenant_slug_header(): void
    {
        [$token] = $this->createTenant('tenant-techfix');

        $this->withHeaders(['X-Tenant-Slug' => 'outra-empresa'])
            ->withToken($token)
            ->getJson('/api/v1/auth/bootstrap')
            ->assertNotFound();
    }

    /**
     * @return array{0:string}
     */
    protected function createTenant(string $slug): array
    {
        $user = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Tenant Routing',
            'slug' => $slug,
            'email' => 'tenant@example.com',
            'is_active' => true,
        ]);

        $membership = Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'tenant-routing')['plain_text_token'];

        return [$token];
    }
}
