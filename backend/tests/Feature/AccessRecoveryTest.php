<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_sends_magic_link_and_returns_200(): void
    {
        Mail::fake();

        [$company, $user] = $this->createMembership();

        $response = $this->postJson('/api/v1/auth/recover-access', [
            'email' => $user->email,
            'company_uuid' => $company->uuid,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Se este e-mail está cadastrado, um link de recuperação foi enviado.');

        $this->assertDatabaseHas('auth_challenges', [
            'user_id' => $user->getKey(),
            'company_id' => $company->getKey(),
            'type' => AuthChallenge::TYPE_MAGIC_LINK,
        ]);

        Mail::assertSent(\App\Domains\Auth\Mail\MagicLinkMail::class);

        $this->assertArrayHasKey('debug_token', $response->json());
    }

    public function test_recovery_still_returns_200_for_unknown_email(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Test Co',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/recover-access', [
            'email' => 'nobody@nowhere.com',
            'company_uuid' => $company->uuid,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Se este e-mail está cadastrado, um link de recuperação foi enviado.');

        Mail::assertNothingSent();
        $this->assertArrayNotHasKey('debug_token', $response->json());
    }

    public function test_recovery_works_regardless_of_magic_link_enabled_flag(): void
    {
        Mail::fake();

        [$company, $user] = $this->createMembership(magicLinkEnabled: false);

        $this->postJson('/api/v1/auth/recover-access', [
            'email' => $user->email,
            'company_uuid' => $company->uuid,
        ])->assertOk();

        Mail::assertSent(\App\Domains\Auth\Mail\MagicLinkMail::class);
    }

    public function test_recovery_token_can_be_consumed_to_authenticate(): void
    {
        Mail::fake();

        [$company, $user] = $this->createMembership();

        $response = $this->postJson('/api/v1/auth/recover-access', [
            'email' => $user->email,
            'company_uuid' => $company->uuid,
        ])->assertOk();

        $token = $response->json('debug_token');
        $this->assertNotNull($token);

        $consume = $this->postJson('/api/v1/auth/passwordless/magic-link/consume', [
            'token' => $token,
        ])->assertOk();

        $this->assertArrayHasKey('access_token', $consume->json());

        $this->withToken($consume->json('access_token'))
            ->getJson('/api/v1/tenant/context')
            ->assertOk()
            ->assertJsonPath('company.uuid', $company->uuid);
    }

    public function test_logout_revokes_token_and_subsequent_requests_fail(): void
    {
        [$company, $user] = $this->createMembership();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ])->assertOk();

        $token = $login->json('access_token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($token)->getJson('/api/v1/tenant/context')->assertUnauthorized();
    }

    /**
     * @return array{0:Company,1:User}
     */
    protected function createMembership(bool $magicLinkEnabled = true): array
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Test Company',
            'email' => 'test@example.com',
            'is_active' => true,
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
