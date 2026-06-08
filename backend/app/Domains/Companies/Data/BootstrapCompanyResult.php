<?php

namespace App\Domains\Companies\Data;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;

readonly class BootstrapCompanyResult
{
    public function __construct(
        public Company $company,
        public User $user,
        public Membership $membership,
        public ApiToken $apiToken,
        public string $plainTextToken,
        public string $plainTextPassword,
    ) {}
}
