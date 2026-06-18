<?php

namespace Tests\Support;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;

class TenantTestContext
{
    public function __construct(
        public readonly Company $company,
        public readonly User $user,
        public readonly Membership $membership,
        public readonly string $token,
    ) {
    }
}
