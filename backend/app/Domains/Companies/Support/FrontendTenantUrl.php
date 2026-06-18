<?php

namespace App\Domains\Companies\Support;

use App\Domains\Companies\Models\Company;

class FrontendTenantUrl
{
    public function base(?Company $company = null): string
    {
        $root = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');

        if ($company === null || ! filled($company->slug)) {
            return $root;
        }

        return $root.'/'.$company->slug;
    }

    public function login(?Company $company = null): string
    {
        return $this->base($company).'/login';
    }

    public function invitation(Company $company, string $token): string
    {
        return $this->base($company).'/convites/'.$token;
    }

    public function magicLink(Company $company, string $token): string
    {
        return $this->base($company).'/auth/magic/'.$token;
    }

    public function billingPlan(?Company $company = null): string
    {
        return $this->base($company).'/configuracoes/plano';
    }
}
