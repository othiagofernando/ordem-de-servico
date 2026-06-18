<?php

namespace Tests\Feature;

use App\Domains\Auth\Mail\WelcomeCompanyMail;
use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_company_user_membership_counter_and_returns_access_token(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'company_name' => 'Nova Assistencia Central',
            'user_name' => 'Thiago Admin',
            'user_email' => 'novo-admin@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('company.name', 'Nova Assistencia Central')
            ->assertJsonPath('company.slug', 'nova-assistencia-central')
            ->assertJsonPath('membership.role', 'administrator')
            ->assertJsonPath('user.email', 'novo-admin@example.com')
            ->assertJsonStructure(['access_token', 'company', 'membership', 'user']);

        $companyId = \App\Domains\Companies\Models\Company::query()->withoutGlobalScopes()
            ->where('name', 'Nova Assistencia Central')
            ->value('id');

        $this->assertNotNull($companyId);

        $this->assertDatabaseHas('users', [
            'email' => 'novo-admin@example.com',
            'name' => 'Thiago Admin',
        ]);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyId,
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('api_tokens', [
            'company_id' => $companyId,
            'name' => 'self-register',
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'slug' => 'nova-assistencia-central',
            'email' => 'novo-admin@example.com',
        ]);

        $counter = CompanyServiceOrderCounter::query()->where('company_id', $companyId)->first();
        $this->assertNotNull($counter);
        $this->assertSame(1, (int) $counter->next_draft_number);
        $this->assertSame(1, (int) $counter->next_service_order_number);

        Mail::assertQueued(WelcomeCompanyMail::class, function (WelcomeCompanyMail $mail): bool {
            return $mail->companyName === 'Nova Assistencia Central'
                && str_ends_with($mail->loginUrl, '/login');
        });

        $this->withToken($response->json('access_token'))
            ->getJson('/api/v1/tenant/context')
            ->assertOk()
            ->assertJsonPath('company.name', 'Nova Assistencia Central')
            ->assertJsonPath('membership.role', 'administrator');
    }

    public function test_public_registration_requires_globally_unique_email(): void
    {
        $payload = [
            'company_name' => 'Primeira Empresa',
            'user_name' => 'Primeiro Admin',
            'user_email' => 'duplicado@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ];

        $this->postJson('/api/v1/register', $payload)->assertCreated();

        $this->postJson('/api/v1/register', [
            ...$payload,
            'company_name' => 'Segunda Empresa',
            'user_name' => 'Segundo Admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_email']);
    }

    public function test_public_registration_rate_limits_by_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/register', [
                'company_name' => 'Empresa '.$attempt,
                'user_name' => 'Admin '.$attempt,
                'user_email' => "admin{$attempt}@example.com",
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/register', [
            'company_name' => 'Empresa 6',
            'user_name' => 'Admin 6',
            'user_email' => 'admin6@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many authentication attempts. Please try again later.');
    }

    public function test_public_registration_generates_unique_slug_for_similar_company_names(): void
    {
        $this->postJson('/api/v1/register', [
            'company_name' => 'Tech Fix',
            'user_name' => 'Admin A',
            'user_email' => 'admin-a@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertCreated()
            ->assertJsonPath('company.slug', 'tech-fix');

        $this->postJson('/api/v1/register', [
            'company_name' => 'Tech Fix',
            'user_name' => 'Admin B',
            'user_email' => 'admin-b@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertCreated()
            ->assertJsonPath('company.slug', 'tech-fix-2');
    }
}
