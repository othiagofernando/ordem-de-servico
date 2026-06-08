<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\Membership;
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
            ->get();
    }
}
