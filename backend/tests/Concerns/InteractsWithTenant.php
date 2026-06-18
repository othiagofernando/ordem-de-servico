<?php

namespace Tests\Concerns;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TenantTestContext;

trait InteractsWithTenant
{
    protected function createTenantContext(
        string $email,
        string $role = 'administrator',
        ?string $companyName = null,
        ?string $userName = null,
        string $tokenName = 'tenant-test',
    ): TenantTestContext {
        $user = User::query()->create([
            'name' => $userName ?? 'Tenant User',
            'email' => $email,
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => $companyName ?? 'Tenant '.str($email)->before('@')->headline(),
            'email' => $email,
            'is_active' => true,
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, $tokenName)['plain_text_token'];

        return new TenantTestContext($company, $user, $membership, $token);
    }
}
