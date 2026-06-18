<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Billing\Mail\BillingLifecycleMail;
use App\Domains\Billing\Models\BillingInvoice;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingAutomatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_checkout_creates_gateway_subscription_and_persists_gateway_ids(): void
    {
        Config::set('billing.asaas.api_key', 'test-key');
        Config::set('billing.asaas.webhook_token', 'webhook-secret');
        Config::set('billing.asaas.force_remote_in_tests', true);

        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_123'], 200),
            '*/subscriptions' => Http::response([
                'id' => 'sub_123',
                'customer' => 'cus_123',
                'invoiceUrl' => 'https://gateway.test/checkout/sub_123',
            ], 200),
        ]);

        [$company, $token] = $this->createTenant();
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/subscription/checkout', [
                'plan_id' => $plan->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://gateway.test/checkout/sub_123');

        $subscription = Subscription::query()->where('company_id', $company->getKey())->firstOrFail();

        $this->assertSame('cus_123', $subscription->gateway_customer_id);
        $this->assertSame('sub_123', $subscription->gateway_subscription_id);
        $this->assertSame(Subscription::STATUS_SUSPENDED, $subscription->status);
    }

    public function test_asaas_webhook_confirms_payment_renews_subscription_and_records_invoice(): void
    {
        Config::set('billing.asaas.webhook_token', 'webhook-secret');
        Mail::fake();

        [$company] = $this->createTenant();
        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = $company->subscription()->firstOrFail();
        $subscription->forceFill([
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_SUSPENDED,
            'gateway_customer_id' => 'cus_abc',
            'gateway_subscription_id' => 'sub_abc',
        ])->save();

        $before = $subscription->expires_at->copy();

        $this->postJson('/api/v1/billing/webhooks/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_001',
                'subscription' => ['id' => 'sub_abc'],
                'customer' => ['id' => 'cus_abc'],
                'status' => 'RECEIVED',
                'value' => 99.00,
                'invoiceUrl' => 'https://gateway.test/invoice/pay_001',
                'bankSlipUrl' => 'https://gateway.test/pdf/pay_001',
                'paymentDate' => now()->toDateString(),
            ],
        ], [
            'asaas-access-token' => 'webhook-secret',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->expires_at->greaterThan($before));
        $this->assertDatabaseHas('billing_invoices', [
            'company_id' => $company->getKey(),
            'gateway_invoice_id' => 'pay_001',
            'status' => 'RECEIVED',
        ]);
        Mail::assertQueued(BillingLifecycleMail::class, fn (BillingLifecycleMail $mail): bool => $mail->subjectLine === 'Pagamento confirmado e assinatura renovada');
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        Config::set('billing.asaas.webhook_token', 'webhook-secret');

        $this->postJson('/api/v1/billing/webhooks/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
        ], [
            'asaas-access-token' => 'wrong-secret',
        ])->assertUnauthorized();
    }

    public function test_asaas_webhook_payment_refused_sends_payment_issue_email(): void
    {
        Config::set('billing.asaas.webhook_token', 'webhook-secret');
        Mail::fake();

        [$company] = $this->createTenant();
        $subscription = $company->subscription()->firstOrFail();
        $subscription->forceFill([
            'gateway_customer_id' => 'cus_refused',
            'gateway_subscription_id' => 'sub_refused',
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        $this->postJson('/api/v1/billing/webhooks/asaas', [
            'event' => 'PAYMENT_REFUSED',
            'payment' => [
                'id' => 'pay_refused',
                'subscription' => ['id' => 'sub_refused'],
                'customer' => ['id' => 'cus_refused'],
                'status' => 'REFUSED',
                'value' => 129.90,
                'invoiceUrl' => 'https://gateway.test/invoice/pay_refused',
                'bankSlipUrl' => 'https://gateway.test/pdf/pay_refused',
                'dueDate' => now()->toDateString(),
            ],
        ], [
            'asaas-access-token' => 'webhook-secret',
        ])->assertOk();

        Mail::assertQueued(BillingLifecycleMail::class, fn (BillingLifecycleMail $mail): bool => $mail->subjectLine === 'Problema na cobranca da assinatura');
    }

    public function test_daily_sync_command_sends_renewal_and_grace_period_emails_once(): void
    {
        Mail::fake();

        [$companyA] = $this->createTenant('tenant-a@example.com', 'Tenant A');
        [$companyB] = $this->createTenant('tenant-b@example.com', 'Tenant B');
        [$companyC] = $this->createTenant('tenant-c@example.com', 'Tenant C');

        $companyA->subscription->forceFill([
            'expires_at' => now()->addDays(15),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        $companyB->subscription->forceFill([
            'expires_at' => now()->addDays(3),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        $companyC->subscription->forceFill([
            'expires_at' => now(),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        Artisan::call('billing:sync-subscriptions');
        Artisan::call('billing:sync-subscriptions');

        Mail::assertQueued(BillingLifecycleMail::class, fn (BillingLifecycleMail $mail): bool => $mail->subjectLine === 'Lembrete de renovacao da assinatura');
        Mail::assertQueued(BillingLifecycleMail::class, fn (BillingLifecycleMail $mail): bool => $mail->subjectLine === 'Renovacao urgente da assinatura');
        Mail::assertQueued(BillingLifecycleMail::class, fn (BillingLifecycleMail $mail): bool => $mail->subjectLine === 'Assinatura vencida: periodo de tolerancia iniciado');
        Mail::assertQueuedCount(3);
    }

    /**
     * @return array{0:\App\Domains\Companies\Models\Company,1:string}
     */
    protected function createTenant(string $email = 'tenant@example.com', string $companyName = 'Tenant Billing'): array
    {
        $user = User::query()->create([
            'name' => 'Tenant User',
            'email' => $email,
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => $companyName,
            'email' => $email,
            'is_active' => true,
        ]);

        $membership = $company->memberships()->create([
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'tenant-billing')['plain_text_token'];

        return [$company, $token];
    }
}
