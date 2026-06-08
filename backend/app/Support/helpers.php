<?php

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Support\CurrentCompanyContext;

if (! function_exists('tenant')) {
    function tenant(): ?Company
    {
        return app(CurrentCompanyContext::class)->company();
    }
}

if (! function_exists('currentCompany')) {
    function currentCompany(): ?Company
    {
        return tenant();
    }
}

if (! function_exists('currentMembership')) {
    function currentMembership(): ?Membership
    {
        return app(CurrentCompanyContext::class)->membership();
    }
}
