<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;

class ResolveCurrentSubscription
{
    public function handle(Company $company): ?Subscription
    {
        return Subscription::query()
            ->with(['plan', 'company'])
            ->where('company_id', $company->getKey())
            ->latest('id')
            ->first();
    }
}
