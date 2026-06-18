<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_subscription_beyond_grace_period_blocks_tenant_routes_with_402(): void
    {
        [$company, $token] = $this->createTenant();

        $company->subscription->forceFill([
            'expires_at' => now()->subDays(5),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        $this->withToken($token)
            ->getJson('/api/v1/settings')
            ->assertStatus(402)
            ->assertJsonPath('subscription.status', Subscription::STATUS_ACTIVE);
    }

    public function test_service_order_creation_is_blocked_when_monthly_plan_limit_is_reached(): void
    {
        [$company, $token] = $this->createTenant();
        $this->assignPlan($company, 'starter');

        [$customerId, $contactId, $templateId] = $this->createDraftDependencies($company->getKey());

        ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'reference' => 'OS-LIMIT-001',
            'title' => 'Base',
            'status' => ServiceOrder::STATUS_OPEN,
            'created_at' => now()->startOfMonth()->addDay(),
            'updated_at' => now(),
        ]);

        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();
        $plan->forceFill(['max_service_orders_per_month' => 1])->save();

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customerId,
                'customer_contact_id' => $contactId,
                'intake_checklist_template_id' => $templateId,
                'equipment_description' => 'Notebook teste',
                'reported_problem' => 'Nao liga',
            ])
            ->assertStatus(402)
            ->assertJsonPath('message', 'O limite mensal de ordens de serviço do plano foi atingido.');
    }

    public function test_super_admin_can_define_and_extend_company_subscription(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        [$company] = $this->createTenant();
        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-billing')['plain_text_token'];
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->withToken($token)
            ->putJson('/api/v1/admin/companies/'.$company->getKey().'/subscription', [
                'plan_id' => $plan->getKey(),
                'expires_at' => now()->addDays(20)->toDateString(),
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'profissional');

        $before = Subscription::query()->where('company_id', $company->getKey())->firstOrFail()->expires_at;

        $this->withToken($token)
            ->postJson('/api/v1/admin/companies/'.$company->getKey().'/subscription/extend', [
                'months' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $after = Subscription::query()->where('company_id', $company->getKey())->firstOrFail()->expires_at;

        $this->assertTrue($after->gt($before));
    }

    /**
     * @return array{0: \App\Domains\Companies\Models\Company, 1: string}
     */
    protected function createTenant(): array
    {
        $user = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = \App\Domains\Companies\Models\Company::query()->create([
            'name' => 'Tenant Billing',
            'email' => 'tenant@example.com',
            'is_active' => true,
        ])->fresh(['subscription.plan']);

        $membership = Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'tenant-billing')['plain_text_token'];

        return [$company, $token];
    }

    protected function assignPlan(\App\Domains\Companies\Models\Company $company, string $slug): void
    {
        $plan = Plan::query()->where('slug', $slug)->firstOrFail();

        $company->subscription->forceFill([
            'plan_id' => $plan->getKey(),
            'started_at' => now()->subDays(3),
            'expires_at' => now()->addDays(30),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    protected function createDraftDependencies(int $companyId): array
    {
        $customer = Customer::query()->create([
            'company_id' => $companyId,
            'customer_type' => 'PF',
            'name' => 'Cliente Billing',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $companyId,
            'customer_id' => $customer->getKey(),
            'label' => 'Principal',
            'name' => 'Cliente Billing',
            'phone' => '11999999999',
            'normalized_phone' => '11999999999',
        ]);

        $template = IntakeChecklistTemplate::query()->create([
            'company_id' => $companyId,
            'category' => 'generic',
            'version' => 1,
            'title' => 'Checklist Base',
            'items' => [['label' => 'Liga']],
        ]);

        return [$customer->getKey(), $contact->getKey(), $template->getKey()];
    }
}
