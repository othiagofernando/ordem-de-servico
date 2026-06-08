<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Data\BootstrapCompanyResult;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapCompany
{
    public function handle(
        string $companyName,
        string $adminName,
        string $adminEmail,
        ?string $password = null,
        ?string $companyDocument = null,
        ?string $companyEmail = null,
        ?string $companyPhone = null,
        string $tokenName = 'bootstrap',
    ): BootstrapCompanyResult {
        $plainTextPassword = $password ?: Str::password(16);
        $plainTextToken = Str::random(64);

        return DB::transaction(function () use (
            $companyName,
            $adminName,
            $adminEmail,
            $plainTextPassword,
            $plainTextToken,
            $companyDocument,
            $companyEmail,
            $companyPhone,
            $tokenName,
        ): BootstrapCompanyResult {
            $company = Company::query()->create([
                'name' => $companyName,
                'document' => $companyDocument,
                'email' => $companyEmail ?: $adminEmail,
                'phone' => $companyPhone,
                'is_active' => true,
            ]);

            $user = User::query()->firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => $plainTextPassword,
                ],
            );

            $membership = Membership::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'role' => 'administrator',
                'status' => 'active',
            ]);

            $apiToken = ApiToken::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'name' => $tokenName,
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now()->addDays(30),
            ]);

            return new BootstrapCompanyResult(
                company: $company,
                user: $user,
                membership: $membership,
                apiToken: $apiToken,
                plainTextToken: $plainTextToken,
                plainTextPassword: $plainTextPassword,
            );
        });
    }
}
