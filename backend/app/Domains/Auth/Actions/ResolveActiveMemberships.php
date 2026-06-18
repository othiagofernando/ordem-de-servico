<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveActiveMemberships
{
    /**
     * @return Collection<int, Membership>
     */
    public function handle(User $user): Collection
    {
        return Membership::query()
            ->withoutGlobalScopes()
            ->with('company')
            ->where('user_id', $user->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('status', Company::STATUS_ACTIVE))
            ->get();
    }
}
