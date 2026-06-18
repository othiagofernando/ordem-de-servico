<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Billing\Gateways\AsaasGateway;
use App\Domains\Companies\Data\BootstrapCompanyResult;
use App\Domains\Companies\Models\Company;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapCompany
{
    public function __construct(
        protected GenerateUniqueCompanySlug $generateUniqueCompanySlug,
        protected IssueApiToken $issueApiToken,
        protected AsaasGateway $billingGateway,
    ) {}

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

        $result = DB::transaction(function () use (
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
                'slug' => $this->generateUniqueCompanySlug->handle($companyName),
                'document' => $companyDocument,
                'email' => $companyEmail ?: $adminEmail,
                'phone' => $companyPhone,
                'is_active' => true,
            ]);

            $user = User::query()->create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $plainTextPassword,
            ]);

            $membership = Membership::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'role' => 'administrator',
                'status' => 'active',
            ]);

            CompanyServiceOrderCounter::query()->create([
                'company_id' => $company->getKey(),
                'next_draft_number' => 1,
                'next_service_order_number' => 1,
            ]);

            $issuedToken = $this->issueApiToken->handle($user, $membership, $tokenName);

            return new BootstrapCompanyResult(
                company: $company,
                user: $user,
                membership: $membership,
                apiToken: $issuedToken['api_token'],
                plainTextToken: $issuedToken['plain_text_token'],
                plainTextPassword: $plainTextPassword,
            );
        });

        $customer = $this->billingGateway->ensureCustomer($result->company);

        $result->company->subscription?->forceFill([
            'gateway_customer_id' => $customer['id'] ?? null,
            'gateway_name' => config('billing.gateway'),
        ])->save();

        return $result;
    }
}
