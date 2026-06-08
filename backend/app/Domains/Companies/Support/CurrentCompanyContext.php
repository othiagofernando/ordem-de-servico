<?php

namespace App\Domains\Companies\Support;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;

class CurrentCompanyContext
{
    protected ?Company $company = null;

    protected ?Membership $membership = null;

    protected ?User $user = null;

    protected ?ApiToken $apiToken = null;

    public function set(Company $company, Membership $membership, User $user, ApiToken $apiToken): void
    {
        $this->company = $company;
        $this->membership = $membership;
        $this->user = $user;
        $this->apiToken = $apiToken;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function apiToken(): ?ApiToken
    {
        return $this->apiToken;
    }
}
