<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_role_preset_resolves_granular_permissions(): void
    {
        [$adminToken, $adminMembership, $technicianMembership] = $this->seedAuthorizationScenario();

        $this->withToken($adminToken)
            ->patchJson('/api/v1/tenant/memberships/'.$technicianMembership->getKey().'/authorization', [
                'role' => 'manager',
                'permissions' => ['memberships.manage'],
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonFragment(['memberships.manage']);

        $this->withToken($adminToken)
            ->getJson('/api/v1/auth/permissions/me')
            ->assertOk()
            ->assertJsonPath('membership.role', 'administrator')
            ->assertJsonFragment(['service_orders.view_any']);

        $technicianMembership->refresh();
        $this->assertTrue($technicianMembership->hasPermission('memberships.manage'));
        $this->assertTrue($technicianMembership->hasPermission('service_orders.view_any'));
    }

    public function test_permissions_differ_by_company_membership_for_the_same_identity(): void
    {
        [$adminToken, , , $user] = $this->seedAuthorizationScenario();

        $companyB = Company::query()->create([
            'name' => 'Other Company',
            'email' => 'other@example.com',
            'is_active' => true,
        ]);

        $otherMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $companyB->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $otherToken = app(IssueApiToken::class)->handle($user, $otherMembership, 'other-company')['plain_text_token'];

        $this->withToken($adminToken)
            ->getJson('/api/v1/auth/permissions/me')
            ->assertOk()
            ->assertJsonFragment(['memberships.manage']);

        $this->withToken($otherToken)
            ->getJson('/api/v1/auth/permissions/me')
            ->assertOk()
            ->assertJsonMissing(['memberships.manage'])
            ->assertJsonFragment(['service_orders.view_assigned']);

        $this->withToken($otherToken)
            ->patchJson('/api/v1/tenant/memberships/'.$otherMembership->getKey().'/authorization', [
                'role' => 'manager',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This membership lacks the [memberships.manage] permission.');
    }

    public function test_technician_only_sees_assigned_service_orders_and_cannot_open_unassigned_records(): void
    {
        [, , $technicianMembership, , $technicianToken] = $this->seedAuthorizationScenario();

        $assignedOrder = ServiceOrder::query()->create([
            'company_id' => $technicianMembership->company_id,
            'reference' => 'SO-1001',
            'title' => 'Assigned repair',
            'assigned_membership_id' => $technicianMembership->getKey(),
        ]);

        $otherMembership = Membership::query()
            ->withoutGlobalScopes()
            ->where('company_id', $technicianMembership->company_id)
            ->where('id', '!=', $technicianMembership->getKey())
            ->firstOrFail();

        $hiddenOrder = ServiceOrder::query()->create([
            'company_id' => $technicianMembership->company_id,
            'reference' => 'SO-1002',
            'title' => 'Hidden repair',
            'assigned_membership_id' => $otherMembership->getKey(),
        ]);

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $assignedOrder->reference);

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders/'.$assignedOrder->getKey())
            ->assertOk()
            ->assertJsonPath('data.reference', $assignedOrder->reference);

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders/'.$hiddenOrder->getKey())
            ->assertNotFound();
    }

    /**
     * @return array{string, Membership, Membership, User, string}
     */
    protected function seedAuthorizationScenario(): array
    {
        $user = User::query()->create([
            'name' => 'Platform User',
            'email' => 'platform@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'platform@example.com',
            'is_active' => true,
        ]);

        $adminMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician User',
            'email' => 'tech@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $technicianMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $technicianUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $adminToken = app(IssueApiToken::class)->handle($user, $adminMembership, 'admin-test')['plain_text_token'];
        $technicianToken = app(IssueApiToken::class)->handle($technicianUser, $technicianMembership, 'tech-test')['plain_text_token'];

        return [$adminToken, $adminMembership, $technicianMembership, $user, $technicianToken];
    }
}
